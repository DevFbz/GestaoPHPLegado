# Sprint 3 — Solicitações TRADE

## Entregue

- criação transacional de solicitações TRADE;
- itens da solicitação vinculados a brindes;
- cálculo do valor total e identificação de necessidade de compra;
- histórico imutável de status;
- envio para aprovação;
- aprovação ou reprovação por perfil autorizado;
- escopo de visualização por solicitante, indústria ou permissão global;
- endpoints versionados em `/api/v1/trade-requests`;
- testes de criação, aprovação e bloqueio do perfil CD/Estoque.

## Regras importantes

- CD/Estoque não cria solicitação TRADE;
- somente o solicitante ou perfil de processamento pode enviar o rascunho;
- somente perfil com `requests.approve` pode aprovar;
- cada mudança de status gera histórico;
- nenhuma solicitação depende de estado da página ou arquivo JSON.

## Próxima sprint

Migrar eventos, entregas, QR, comprovantes e baixa de estoque vinculada à aprovação/retirada.
