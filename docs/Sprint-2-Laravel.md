# Sprint 2 — Identidade, permissões e importação segura

## Entregue

- autenticação por token via Laravel Sanctum;
- endpoint de login, usuário atual e logout;
- perfis Administrador, TRADE/Gestor, CD/Estoque, TRADE e Indústria;
- permissões granulares no banco;
- middleware de autorização por permissão;
- importador transacional dos JSON de cadastros e brindes;
- modo `--dry-run` para simular sem gravar;
- testes de autenticação e restrição de perfil.

## Segurança da importação

Usuários não são criados automaticamente porque os JSON exportados não contêm senhas. O importador informa a quantidade ignorada para que as contas sejam criadas por fluxo seguro de convite ou redefinição de senha.

## Comandos

```powershell
php artisan db:seed
php artisan brindes:import-json --dry-run
php artisan brindes:import-json
php artisan test
```

## Critério de aceite

Um usuário autenticado precisa receber apenas as permissões do seu perfil. Uma entrada ou saída deve continuar registrada depois de uma nova requisição, e uma importação interrompida deve fazer rollback completo.
