# Requisitos de VM — Controle de Brindes

## 1. Objetivo

Este documento define a VM recomendada para hospedar a implantação produtiva do sistema. A VM é a opção mais simples para manter PHP, banco, arquivos, cron e workers sob controle da empresa.

## 2. Perfil mínimo para homologação

| Recurso | Mínimo |
|---|---|
| Sistema operacional | Ubuntu Server 22.04/24.04 LTS 64 bits |
| CPU | 2 vCPU |
| Memória | 4 GB RAM |
| Disco | 40 GB SSD |
| Rede | IPv4 público, DNS e portas 80/443 |
| PHP | 8.2 ou 8.3 com PHP-FPM |
| Banco | MariaDB 10.11 ou MySQL 8 |
| Web server | Nginx |
| TLS | Let's Encrypt/Certbot |
| Backup | destino externo ou segundo volume |

## 3. Perfil recomendado para produção inicial

| Recurso | Recomendado |
|---|---|
| CPU | 4 vCPU |
| Memória | 8 GB RAM |
| Disco | 80 GB SSD NVMe |
| Sistema | Ubuntu Server 24.04 LTS |
| Banco | MariaDB 10.11 em volume persistente ou banco gerenciado |
| Backup | diário local + cópia externa, retenção mínima de 14 dias |
| Disponibilidade | IP fixo, monitoramento HTTP e alerta de disco |

Para volume alto, separar o banco e o storage da aplicação em serviços distintos.

## 4. Pacotes e extensões PHP

```bash
sudo apt update
sudo apt install -y nginx mariadb-server unzip git curl supervisor certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd \
  php8.3-curl php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath
```

Validar:

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|gd|fileinfo|openssl|zip|xml|dom|intl|curl|bcmath'
mysql --version
nginx -v
```

Se o backend Laravel for usado com PostgreSQL, instalar também `php8.3-pgsql` e usar PostgreSQL persistente ou gerenciado.

## 5. Armazenamento e diretórios

- Document root: `/var/www/brindes/public`.
- Código fora da área pública: `/var/www/brindes/app`, `/config`, `/database`, `/storage`.
- `storage/` deve ser gravável pelo usuário do PHP-FPM.
- NFs, fotos, assinaturas e PDFs não devem ser publicados diretamente.
- Backups devem ser copiados para outro host, bucket ou volume.

Exemplo:

```bash
sudo chown -R www-data:www-data /var/www/brindes/storage
sudo chmod -R 775 /var/www/brindes/storage
```

## 6. Banco de dados

Criar banco e usuário dedicado, sem usar `root` na aplicação:

```sql
CREATE DATABASE brindes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'brindes_app'@'localhost' IDENTIFIED BY '<senha forte fora do Git>';
GRANT ALL PRIVILEGES ON brindes.* TO 'brindes_app'@'localhost';
FLUSH PRIVILEGES;
```

Configuração do legado:

```env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=brindes
DB_USER=brindes_app
DB_PASS=<senha forte>
```

Configuração do Laravel, se o backend for o destino principal, deve seguir as variáveis Laravel e a migration correspondente.

## 7. Nginx e HTTPS

- Apontar o domínio para a VM.
- Configurar `root /var/www/brindes/public`.
- Encaminhar PHP para PHP-FPM.
- Bloquear `.env`, `app/`, `config/`, `database/` e `storage/`.
- Emitir certificado:

```bash
sudo certbot --nginx -d brindes.exemplo.com.br
```

- Redirecionar HTTP para HTTPS.
- Habilitar firewall somente para SSH, HTTP e HTTPS; restringir SSH por IP quando possível.

## 8. Processos e tarefas

Cron mínimo:

```cron
* * * * * www-data php /var/www/brindes/cron/send_notifications.php
5 2 * * * www-data php /var/www/brindes/cron/daily_alerts.php
10 2 * * * www-data php /var/www/brindes/cron/backup.php
```

E-mail não é requisito desta entrega. Se os scripts de notificação não forem usados, não habilitar SMTP apenas para cumprir o cron.

Para jobs Laravel, usar Supervisor ou outro worker persistente:

```ini
[program:brindes-worker]
command=/usr/bin/php /var/www/brindes/backend/artisan queue:work --sleep=3 --tries=3
directory=/var/www/brindes/backend
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/brindes-worker.log
```

## 9. Segurança operacional

- Não colocar segredos no repositório.
- Usar chave SSH, firewall e atualizações automáticas de segurança.
- Desativar login SSH por senha quando houver chave configurada.
- Não expor MariaDB/PostgreSQL à Internet sem necessidade.
- Aplicar menor privilégio ao usuário do banco.
- Monitorar espaço em disco, carga, RAM, PHP-FPM e banco.
- Testar restauração dos backups, não apenas a criação.

## 10. Checklist de aceite da VM

- [ ] DNS aponta para o IP correto.
- [ ] HTTPS válido e renovação automática funcionando.
- [ ] Login e troca obrigatória de senha funcionando.
- [ ] Entrada, saída, ajuste e histórico persistem após reinício.
- [ ] Solicitação percorre aprovação, recebimento e retirada.
- [ ] PDF, QR, assinatura e anexos são recuperáveis.
- [ ] Perfis não acessam dados fora do escopo.
- [ ] Cron/worker executa sem erro.
- [ ] Backup restaura em uma VM ou banco de teste.
- [ ] Logs não contêm tokens, senhas ou APP_KEY.
