# Esquema Backend — Controle de Brindes

## 1. Módulos e responsabilidades

| Camada | Responsabilidade | Local |
|---|---|---|
| Entrada HTTP | Front controller, sessão e headers | `public/index.php`, `app/Core/App.php` |
| Roteamento | Rotas JSON e páginas | `app/routes.php`, `app/pages.php` |
| Controllers | Autorização de endpoint e resposta | `app/Controllers/` |
| Services | Regras, transações e integrações | `app/Services/` |
| Support | Menu, QR, seed, JSON, instalação | `app/Support/` |
| Persistência | PDO, transação, idempotência | `app/Core/Db.php`, `app/Core/Idempotency.php` |
| Apresentação | Views e scripts Alpine | `resources/views/`, `public/assets/js/` |

## 2. Modelo lógico

```text
users -> roles -> permissions
users -> departments / industries
items -> categories / locations / suppliers
items -> stock -> stock_positions
items -> stock_movements
requests -> request_items -> items
requests -> request_status_history
requests -> approvals -> approval_rules
requests -> deliveries -> delivery_items
events -> event_allocations -> industries + items
users -> notifications
users -> audit_log
```

## 3. Fonte de verdade

- MySQL/MariaDB mantém transações, relações e locks.
- `stock_movements` é um livro imutável; o saldo atual fica em `stock`.
- `requests.flow` separa `interna` e `trade`.
- `deleted_at` implementa lixeira para cadastros.
- `JsonDatabase` é espelho operacional e não substitui o SQL.

## 4. Contratos de integração

### Lecom

```text
Portal homologação: configurável em settings
API: https://api.lecom.com.br/service/bpm/api/v1/process-instances
Headers: apikey, X-Server, opcionalmente test-mode/test-user
Payload: processId, version, language=pt_BR
Retorno: processInstanceId, activityInstanceId, cycle
```

O host e a chave vêm do ambiente/configuração. O frontend só recebe a URL final do formulário.

### Microsoft 365

```text
PHPMailer -> smtp.office365.com:587 -> STARTTLS -> mailbox dedicada
```

A fila de notificações evita bloquear a operação principal. O worker envia até o limite configurado e registra falhas para diagnóstico.

## 5. API e segurança

- API responde `{ok:true,data,meta}` ou `{ok:false,error}`.
- Escritas exigem CSRF.
- Rotas de mutação importantes usam `Idempotency-Key`.
- Controllers chamam `Auth::authorize` ou recebem permissão na configuração da rota.
- Dados de usuário, senha, token SMTP e Lecom não entram em respostas.

## 6. Evolução recomendada

1. Consolidar testes de contrato para cada API pública.
2. Adicionar job de limpeza de outbox e reprocessamento de e-mails.
3. Migrar a persistência demo da Vercel para um banco persistente quando houver uso real.
4. Criar um serviço Python separado apenas para tarefas de IA, sem acoplá-lo às transações PHP.
