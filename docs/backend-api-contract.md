# Contratos incrementais — API Laravel (`/api/v1`)

Este documento registra os endpoints implementados em `backend/`. O contrato legado em `api-contract.md` continua descrevendo a aplicação antiga e não deve ser considerado automaticamente compatível com esta API versionada.

## Entregas e protocolos

| Método e rota | Acesso | Comportamento |
|---|---|---|
| `POST /api/v1/trade-requests/{id}/deliver` | Sanctum; `stock.exit_confirm` ou `requests.process` | Recebe `received_by_name`, `signature` como data URI PNG, `idempotency_key` e campos opcionais do recebedor/observações. Persiste a assinatura no disco configurado em `FILESYSTEM_DISK`; em produção, use S3/Supabase Storage. Gera protocolo e baixa de estoque transacionalmente. |
| `GET /api/v1/deliveries` | Sanctum + `deliveries.view` | Lista paginada; usuário vinculado a uma indústria recebe somente os protocolos dessa indústria. |
| `GET /api/v1/deliveries/{id}` | Sanctum + `deliveries.view` | Detalhe; respeita o escopo da indústria. |
| `GET /api/v1/deliveries/{id}/qr` | Sanctum + `deliveries.view` | Retorna `code`, URL assinada, QR SVG em `data_uri`, formato e validade de sete dias. Respeita escopo da indústria. |
| `GET /api/v1/deliveries/{id}/pdf` | Sanctum + `deliveries.view` | Baixa o protocolo PDF com itens, QR e assinatura. Se GD não estiver instalado, registra hash verificável no lugar da imagem. Respeita escopo da indústria. |
| `GET /api/v1/verify/{code}?expires=…&signature=…` | URL assinada temporária | Verificação pública limitada a protocolo, tipo e data; não retorna dados pessoais nem saldos. |

A assinatura deve ser um data URI `data:image/png;base64,...` válido, até 2 MB. A chave de idempotência só pode ser repetida com o mesmo corpo; outra carga usando a mesma chave é rejeitada com HTTP 422.

## Autenticação

- `POST /api/v1/auth/login` é limitado a cinco tentativas por 15 minutos.
- Tokens Sanctum expiram após `AUTH_TOKEN_EXPIRATION_MINUTES` (padrão: 480 minutos).
- `POST /api/v1/auth/change-password` recebe `current_password`, `new_password` e `new_password_confirmation`; nova senha precisa ter ao menos oito caracteres, letras e números.
- Com `must_change_password=true`, chamadas autenticadas fora de `auth/me`, `auth/logout` e `auth/change-password` retornam 403 com código `PASSWORD_CHANGE_REQUIRED` até a troca.
- `POST /api/v1/auth/logout` revoga o token atual.

## Validação local

Em `backend/`, executar `C:/Users/Iris/.config/herd-lite/bin/php.exe artisan test --no-coverage`. A emissão do PDF foi exercitada no SQLite de testes; a assinatura visual no PDF exige a extensão GD no PHP do ambiente.
