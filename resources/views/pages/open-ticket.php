<?php
$code = (int) setting('lecom_process_id', '26');
$version = (int) setting('lecom_process_version', '10');
$portal = trim((string) setting('lecom_portal_url', ''));
$apiBase = rtrim(trim((string) setting('lecom_api_base_url', 'https://api.lecom.com.br/service/bpm/api')), '/');
$apiEndpoint = $apiBase . '/v1/process-instances';
$portalHost = $portal !== '' ? parse_url($portal, PHP_URL_HOST) : null;
$workspaceEndpoint = $portal !== '' ? rtrim($portal, '/') . '/workspace/api/process/start?' . http_build_query([
    'processId' => $code,
    'version' => $version,
], '', '&', PHP_QUERY_RFC3986) : '';
$startEndpoint = url('/api/lecom/process/start');
ob_start(); ?>
<div class="card card-body" style="max-width:760px">
  <h2 class="h5">Abrir chamado de suprimentos</h2>
  <p class="text-muted">Processo Lecom: código <?= e((string) $code) ?> · versão <?= e((string) $version) ?>.</p>
  <?php if ($portal !== ''): ?>
    <button class="btn btn-primary align-self-start" type="button" id="lecomStartButton">
      <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Abrir chamado no Lecom
    </button>
    <p class="small text-muted mt-2 mb-0">Em homologação, a instância é criada pelo Workspace usando o cookie SSO do Lecom e o formulário é aberto diretamente.</p>
  <?php else: ?>
    <div class="alert alert-warning mb-3">O endereço do portal Lecom ainda não foi configurado.</div>
    <p class="small text-muted mb-0">Peça ao Administrador para informar o endereço base do portal Lecom em Configurações. O sistema usará automaticamente o código 26 e a versão 10.</p>
  <?php endif; ?>
  <hr>
  <details class="small">
    <summary class="fw-semibold">Como o link de criação é formado pela API</summary>
    <p class="mt-2 mb-2">A biblioteca documenta a abertura via <code>POST</code> para:</p>
    <p><code><?= e($apiEndpoint) ?></code></p>
    <p class="mb-1">Cabeçalhos: <code>apikey</code> (somente no servidor) e <code>X-Server: <?= e((string) ($portalHost ?: 'dominio-do-portal')) ?></code>.</p>
    <pre class="mb-2"><code><?= e(json_encode(['processId' => $code, 'version' => $version, 'language' => 'pt_BR'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
    <p class="mb-1">No script local da biblioteca, o fluxo SSO usa <code>PUT</code> para:</p>
    <p><code><?= e($workspaceEndpoint !== '' ? $workspaceEndpoint : '{portal}/workspace/api/process/start?processId=' . $code . '&version=' . $version) ?></code></p>
    <p class="mb-2">Em homologação, essa rota depende do cookie SSO do Lecom e deve ser chamada dentro do portal, enviando o valor do cookie <code>LecomSSOTicket</code> no header <code>ticket-sso</code>. A chamada server-to-server via <code>LECOM_API_KEY</code> permanece como fallback.</p>
    <p class="mb-2">Se a criação pelo Workspace não estiver disponível, a interface alternativa fica em <code><?= e(($portal !== '' ? rtrim($portal, '/') : '{portal}') . '/form-web/?processId=' . $code . '&version=' . $version . '&newWS=true') ?></code>.</p>
    <p class="mb-0">O formulário da instância é aberto em <code><?= e(($portal !== '' ? rtrim($portal, '/') : '{portal}') . '/workspace/form-app/{processInstanceId}/1/1?isNewForm=true') ?></code>.</p>
  </details>
</div>
<?php
$content = ob_get_clean();
$scripts = '<script>
(function () {
  const button = document.getElementById("lecomStartButton");
  if (!button) return;
  const portal = ' . json_script($portal) . ';
  const processId = ' . json_script($code) . ';
  const processVersion = ' . json_script($version) . ';
  const fallbackEndpoint = ' . json_script($startEndpoint) . ';

  function readCookie(name) {
    const target = String(name).toLowerCase();
    const pair = document.cookie.split(";").map((part) => part.trim()).find((part) => {
      const separator = part.indexOf("=");
      return separator > 0 && part.slice(0, separator).toLowerCase() === target;
    });
    if (!pair) return "";
    const index = pair.indexOf("=");
    try { return decodeURIComponent(pair.slice(index + 1)); } catch (_) { return pair.slice(index + 1); }
  }

  function lecomTicket() {
    return readCookie("LecomSSOTicket") || readCookie("lecomssoticket");
  }

  function findValue(value, key) {
    if (!value || typeof value !== "object") return "";
    if (value[key] !== undefined && value[key] !== null && String(value[key]) !== "") return String(value[key]);
    for (const child of Object.values(value)) {
      const found = findValue(child, key);
      if (found) return found;
    }
    return "";
  }

  async function readResponse(response) {
    const raw = await response.text();
    let data = null;
    try { data = raw ? JSON.parse(raw) : null; } catch (_) {}
    return { response, data };
  }

  async function startWithWorkspaceSso() {
    const ticket = lecomTicket();
    if (!ticket) {
      const error = new Error("Cookie LecomSSOTicket não encontrado. Abra o sistema no mesmo ambiente de homologação do Lecom e tente novamente.");
      error.code = "LECOM_SSO_TICKET_MISSING";
      throw error;
    }

    const base = String(portal || window.location.origin).replace(/\/+$/, "");
    const endpoint = base + "/workspace/api/process/start?processId="
      + encodeURIComponent(processId) + "&version=" + encodeURIComponent(processVersion);
    const headers = {
      "Content-Type": "application/json;charset=UTF-8",
      "Accept": "application/json, text/plain, */*",
      "language": "pt_BR",
      "ticket-sso": ticket
    };
    if (readCookie("testMode").toLowerCase() === "true") {
      headers["test-mode"] = "true";
      const testUser = readCookie("LecomEnvironmentMode");
      if (testUser) headers["test-user"] = testUser;
    }

    let result;
    try {
      result = await readResponse(await fetch(endpoint, {
        method: "PUT",
        headers,
        body: "{}",
        credentials: "include",
        cache: "no-store"
      }));
    } catch (error) {
      const wrapped = new Error("Não foi possível chamar o Workspace do Lecom. Este sistema precisa estar no mesmo domínio do portal de homologação ou ter CORS liberado pelo Lecom.");
      wrapped.code = "LECOM_SSO_NETWORK_ERROR";
      wrapped.cause = error;
      throw wrapped;
    }

    if (!result.response.ok) {
      const message = result.response.status === 403
        ? "O Lecom recusou a abertura do processo. Verifique sua permissão no processo e o login de homologação."
        : "O Workspace do Lecom recusou a criação da instância (HTTP " + result.response.status + ").";
      const error = new Error(message);
      error.code = "LECOM_SSO_START_FAILED";
      error.status = result.response.status;
      throw error;
    }

    const processInstanceId = findValue(result.data, "processInstanceId");
    if (!processInstanceId) {
      const error = new Error("O Lecom respondeu sem informar o processInstanceId da nova instância.");
      error.code = "LECOM_SSO_INVALID_RESPONSE";
      throw error;
    }

    const activityInstanceId = findValue(result.data, "activityInstanceId") || "1";
    const cycle = findValue(result.data, "cycle") || "1";
    return base + "/workspace/form-app/" + encodeURIComponent(processInstanceId) + "/"
      + encodeURIComponent(activityInstanceId) + "/" + encodeURIComponent(cycle) + "?isNewForm=true";
  }

  async function startWithServerFallback() {
    return Api.postIdem(fallbackEndpoint, {});
  }

  button.addEventListener("click", async function () {
    const tab = window.open("about:blank", "_blank");
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    try {
      let target;
      try {
        target = await startWithWorkspaceSso();
      } catch (ssoError) {
        // Em uma aplicação hospedada fora do domínio Lecom, o navegador não
        // consegue ler o cookie SSO. Nesse caso, usa a API server-to-server,
        // cuja chave fica somente no ambiente do PHP/Vercel.
        if (ssoError.code !== "LECOM_SSO_TICKET_MISSING") throw ssoError;
        const fallback = await startWithServerFallback();
        target = fallback && fallback.data && fallback.data.url;
      }
      if (!target) throw new Error("O Lecom não retornou o endereço do formulário.");
      if (tab && !tab.closed) tab.location.href = target;
      else window.location.href = target;
    } catch (error) {
      if (tab && !tab.closed) tab.close();
      if (window.UI && typeof UI.toast === "function") UI.toast(error.message, "err");
      else window.alert(error.message);
    } finally {
      button.disabled = false;
      button.removeAttribute("aria-busy");
    }
  });
})();
</script>';
include BASE_PATH . '/resources/views/layouts/app.php';
