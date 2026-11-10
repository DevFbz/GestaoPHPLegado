using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.Text.Json;
using System.Windows.Forms;

namespace GestaoBrindesOffline;

internal static class Program
{
    private static readonly HttpClient Http = new() { Timeout = TimeSpan.FromSeconds(2) };

    [STAThread]
    private static int Main(string[] args)
    {
        ApplicationConfiguration.Initialize();
        try
        {
            return Run(
                args.Contains("--smoke", StringComparer.OrdinalIgnoreCase),
                args.Contains("--export-json", StringComparer.OrdinalIgnoreCase));
        }
        catch (Exception error)
        {
            if (args.Contains("--smoke", StringComparer.OrdinalIgnoreCase))
            {
                Console.Error.WriteLine(error.Message);
            }
            else
            {
                MessageBox.Show(error.Message, "Controle de Brindes Offline", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
            return 1;
        }
    }

    private static int Run(bool smoke, bool exportJson)
    {
        var packageRoot = AppContext.BaseDirectory;
        var appRoot = Path.Combine(packageRoot, "app");
        var runtime = Path.Combine(packageRoot, "runtime", "php");
        var php = Path.Combine(runtime, "php.exe");
        var ini = Path.Combine(runtime, "php.ini");
        RequireFile(php, "Runtime PHP ausente no pacote.");
        RequireFile(ini, "Configuração PHP ausente no pacote.");
        RequireFile(Path.Combine(appRoot, "bin", "install.php"), "Aplicativo PHP ausente ou incompleto.");
        RequireFile(Path.Combine(appRoot, "vendor", "autoload.php"), "Dependências PHP ausentes; gere novamente o pacote.");

        var dataRoot = Environment.GetEnvironmentVariable("BRINDES_OFFLINE_DATA_DIR");
        if (string.IsNullOrWhiteSpace(dataRoot))
        {
            dataRoot = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "GestaoBrindes", "Offline");
        }
        dataRoot = Path.GetFullPath(dataRoot);
        var sessions = Path.Combine(dataRoot, "sessions");
        var logs = Path.Combine(dataRoot, "logs");
        Directory.CreateDirectory(sessions);
        Directory.CreateDirectory(logs);
        var database = Path.Combine(dataRoot, "brindes.sqlite");
        var keyFile = Path.Combine(dataRoot, "app.key");
        var key = LoadOrCreateKey(keyFile);

        var port = ReserveLoopbackPort();
        var address = $"http://127.0.0.1:{port}";
        var environment = CreateEnvironment(dataRoot, database, key, address);
        if (!File.Exists(database) || new FileInfo(database).Length == 0)
        {
            RunPhp(php, ini, appRoot, environment, ["bin/install.php"], Path.Combine(logs, "install.log"), TimeSpan.FromMinutes(2));
        }

        using var server = StartServer(php, ini, appRoot, environment, port, sessions, Path.Combine(logs, "server.log"));
        try
        {
            WaitForHealthyServer(server, address);
            if (smoke && !exportJson)
            {
                File.WriteAllText(Path.Combine(dataRoot, "smoke-ok.txt"), "OFFLINE_SMOKE_OK");
                return 0;
            }
            if (exportJson)
            {
                var exportDirectory = Path.Combine(dataRoot, "exports");
                Directory.CreateDirectory(exportDirectory);
                RunPhp(php, ini, appRoot, environment,
                    ["bin/export-json.php", $"--dir={exportDirectory}"],
                    Path.Combine(logs, "export-json.log"), TimeSpan.FromMinutes(2));
                if (smoke)
                {
                    File.WriteAllText(Path.Combine(dataRoot, "smoke-export-ok.txt"), "OFFLINE_JSON_EXPORT_OK");
                }
                else
                {
                    MessageBox.Show($"Exportação JSON concluída.\n\nPasta: {exportDirectory}",
                        "Controle de Brindes Offline", MessageBoxButtons.OK, MessageBoxIcon.Information);
                }
                return 0;
            }

            var edge = FindEdge();
            var browserData = Path.Combine(dataRoot, "browser-profile");
            Directory.CreateDirectory(browserData);
            using var browser = Process.Start(new ProcessStartInfo(edge)
            {
                UseShellExecute = false,
                ArgumentList = { "--no-first-run", "--no-default-browser-check", $"--user-data-dir={browserData}", $"--app={address}/login" }
            }) ?? throw new InvalidOperationException("Não foi possível iniciar o Microsoft Edge.");
            browser.WaitForExit();
            return 0;
        }
        finally
        {
            StopServer(server);
        }
    }

    private static Dictionary<string, string> CreateEnvironment(string dataRoot, string database, string key, string address)
    {
        var environment = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
        {
            ["APP_ENV"] = "local",
            ["APP_DEBUG"] = "false",
            ["APP_URL"] = address,
            ["APP_KEY"] = key,
            ["APP_TIMEZONE"] = "America/Sao_Paulo",
            ["DB_DRIVER"] = "sqlite",
            ["DB_PATH"] = database,
            ["APP_STORAGE_PATH"] = Path.Combine(dataRoot, "storage"),
            ["SESSION_DRIVER"] = "file",
            ["SESSION_IDLE_MINUTES"] = "480",
            ["MAIL_DRIVER"] = "log",
            ["LECOM_API_KEY"] = "",
            ["LECOM_TEST_MODE"] = "false",
            ["LECOM_TEST_USER"] = "",
            ["LECOM_HTTP_TIMEOUT"] = "1",
            ["LECOM_CONNECT_TIMEOUT"] = "1",
            ["OFFLINE_DATA_DIR"] = dataRoot
        };
        foreach (var name in new[] { "VERCEL", "VERCEL_URL", "VERCEL_PROJECT_PRODUCTION_URL", "JSON_DB_PATH", "JSON_DB_MIRROR" })
        {
            environment[name] = "";
        }
        return environment;
    }

    private static Process StartServer(string php, string ini, string appRoot, Dictionary<string, string> environment, int port, string sessions, string logPath)
    {
        var start = NewPhpStartInfo(php, ini, appRoot, environment, logPath);
        start.ArgumentList.Add("-d");
        start.ArgumentList.Add($"session.save_path={sessions}");
        start.ArgumentList.Add("-S");
        start.ArgumentList.Add($"127.0.0.1:{port}");
        start.ArgumentList.Add("-t");
        start.ArgumentList.Add("public");
        start.ArgumentList.Add("public/index.php");
        var writer = new StreamWriter(new FileStream(logPath, FileMode.Append, FileAccess.Write, FileShare.ReadWrite)) { AutoFlush = true };
        var process = new Process { StartInfo = start, EnableRaisingEvents = true };
        process.OutputDataReceived += (_, e) => { if (e.Data is not null) lock (writer) writer.WriteLine(e.Data); };
        process.ErrorDataReceived += (_, e) => { if (e.Data is not null) lock (writer) writer.WriteLine(e.Data); };
        process.Exited += (_, _) => writer.Dispose();
        if (!process.Start()) throw new InvalidOperationException("Não foi possível iniciar o servidor PHP local.");
        process.BeginOutputReadLine();
        process.BeginErrorReadLine();
        return process;
    }

    private static ProcessStartInfo NewPhpStartInfo(string php, string ini, string appRoot, Dictionary<string, string> environment, string logPath)
    {
        var start = new ProcessStartInfo(php)
        {
            WorkingDirectory = appRoot,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true
        };
        start.ArgumentList.Add("-c");
        start.ArgumentList.Add(ini);
        foreach (var (key, value) in environment)
        {
            start.Environment[key] = value;
        }
        start.Environment["BRINDES_LOG_PATH"] = logPath;
        return start;
    }

    private static void RunPhp(string php, string ini, string appRoot, Dictionary<string, string> environment, string[] arguments, string logPath, TimeSpan timeout)
    {
        var start = new ProcessStartInfo(php)
        {
            WorkingDirectory = appRoot,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true
        };
        start.ArgumentList.Add("-c");
        start.ArgumentList.Add(ini);
        foreach (var (key, value) in environment) start.Environment[key] = value;
        foreach (var argument in arguments) start.ArgumentList.Add(argument);
        using var process = Process.Start(start) ?? throw new InvalidOperationException("Não foi possível inicializar o banco SQLite local.");
        var stdout = process.StandardOutput.ReadToEndAsync();
        var stderr = process.StandardError.ReadToEndAsync();
        if (!process.WaitForExit((int)timeout.TotalMilliseconds))
        {
            process.Kill(true);
            throw new TimeoutException("A instalação do banco SQLite demorou mais que o esperado.");
        }
        File.WriteAllText(logPath, stdout.GetAwaiter().GetResult() + stderr.GetAwaiter().GetResult());
        if (process.ExitCode != 0)
        {
            throw new InvalidOperationException("Falha ao preparar o banco SQLite local. Consulte logs/install.log na pasta de dados do aplicativo.");
        }
    }

    private static void WaitForHealthyServer(Process server, string address)
    {
        var until = DateTime.UtcNow.AddSeconds(20);
        Exception? last = null;
        while (DateTime.UtcNow < until)
        {
            if (server.HasExited) throw new InvalidOperationException("O servidor local encerrou inesperadamente. Consulte logs/server.log na pasta de dados.");
            try
            {
                using var response = Http.GetAsync(address + "/api/health").GetAwaiter().GetResult();
                using var json = JsonDocument.Parse(response.Content.ReadAsStringAsync().GetAwaiter().GetResult());
                var data = json.RootElement.GetProperty("data");
                if (response.StatusCode == HttpStatusCode.OK
                    && data.GetProperty("status").GetString() == "ok"
                    && data.GetProperty("database").GetString() == "ok") return;
            }
            catch (Exception error) { last = error; }
            Thread.Sleep(250);
        }
        throw new InvalidOperationException("O aplicativo local não ficou pronto em 20 segundos. Consulte logs/server.log na pasta de dados.", last);
    }

    private static int ReserveLoopbackPort()
    {
        using var listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        var port = ((IPEndPoint)listener.LocalEndpoint).Port;
        listener.Stop();
        return port;
    }

    private static string LoadOrCreateKey(string path)
    {
        if (File.Exists(path)) return File.ReadAllText(path).Trim();
        var key = Convert.ToHexString(System.Security.Cryptography.RandomNumberGenerator.GetBytes(32));
        try { File.WriteAllText(path, key); }
        catch (IOException) { if (!File.Exists(path)) throw; return File.ReadAllText(path).Trim(); }
        return key;
    }

    private static string FindEdge()
    {
        foreach (var root in new[] { Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles) })
        {
            var candidate = Path.Combine(root, "Microsoft", "Edge", "Application", "msedge.exe");
            if (File.Exists(candidate)) return candidate;
        }
        throw new FileNotFoundException("Microsoft Edge não encontrado. Instale/ative o Edge neste tablet para abrir o aplicativo offline.");
    }

    private static void StopServer(Process server)
    {
        try
        {
            if (!server.HasExited) server.Kill(true);
            server.WaitForExit(5000);
        }
        catch (InvalidOperationException) { }
    }

    private static void RequireFile(string path, string message)
    {
        if (!File.Exists(path)) throw new FileNotFoundException(message, path);
    }
}
