# Persistência JSON no Vercel

## Decisão e escopo

O app mantém SQLite apenas como cache de consulta da instância PHP. O estado persistente passa a ser um conjunto de documentos JSON privados no Vercel Blob; não há migração para um banco relacional.

Storage: `brindes-json-sync`, acesso privado, região `gru1`. A variável secreta `BLOB_READ_WRITE_TOKEN` e a configuração não secreta `BLOB_STORE_ID` ficam vinculadas somente ao ambiente Production do projeto `gestao-brinde`; nenhum dos valores deve ser copiado para logs ou documentação.

## Documentos

O estado é dividido por domínio para evitar um arquivo monolítico:

- `access.json`: usuários, papéis, permissões e estrutura organizacional;
- `catalog.json`: catálogo, categorias, fornecedores e locais;
- `inventory.json`: brindes, posições, saldos, movimentos e saídas;
- `requests.json`: solicitações, itens, aprovações e regras;
- `deliveries.json`: entregas e itens entregues;
- `events.json`: eventos e alocações;
- `system.json`: notificações, auditoria, configurações (exceto `app.key`), tentativas e sequências.

Cada gravação gera arquivos versionados. O manifesto `brindes-json/v1/current.json` só é publicado depois que os documentos da versão foram enviados; os nomes e caminhos dos arquivos são validados antes do download e a restauração exige exatamente os sete domínios previstos. No primeiro boot sem manifesto, a aplicação publica explicitamente um baseline completo, mesmo se o instalador não produzir SQL rastreado. Linhas restauradas são verificadas contra as colunas reais do esquema SQLite antes de qualquer limpeza ou gravação. Instâncias novas reconstroem o cache SQLite na ordem validada por referências estrangeiras. Tabelas que contêm hash de senha são guardadas somente no Blob privado. A chave `app.key` não é exportada para os documentos.

## Concorrência e falhas

A publicação do manifesto usa ETag (`x-if-match`). Se outra instância publicar primeiro, a gravação antiga não sobrescreve o estado confirmado: a API devolve HTTP 409 (`CONCURRENT_UPDATE`), a cópia local é invalidada e a tela deve ser atualizada antes de tentar novamente. Falhas de upload também impedem uma resposta de sucesso.

O espelho local em `/tmp` é temporário e nunca é considerado prova de persistência. No próximo request, a instância compara a revisão remota com o marcador local e confere a impressão digital do SQLite; se houve alteração local que não chegou ao Blob, a cópia é descartada e reidratada. O runtime envia somente os domínios alterados; se não conseguir identificar a tabela alterada, envia todos os domínios por segurança.

## Limites operacionais

Blob armazena arquivos; esta solução não fornece transações relacionais entre domínios nem substitui um banco multiusuário. Compare-and-swap evita sobrescrita silenciosa, mas operações concorrentes podem receber 409 e exigir nova leitura/reenvio. Arquivos versionados antigos permanecem no storage; monitorar o uso e não apagar versões referenciadas pelo manifesto atual.

## Verificação

- `php tests/json_persistence.php`: cobre round-trip de múltiplos domínios, ETag, conflito concorrente, rejeição de colunas fora do esquema, restauração povoada com verificação de chaves estrangeiras, cache ausente/alterado e gravação do baseline;
- `php tests/ui_regressions.php`: cobre integração, restauração, snapshot inicial completo e erro explícito de concorrência.
- `php tests/scenarios.php`: a execução normal é bloqueada porque o MySQL local em `127.0.0.1` recusa conexões. Com um SQLite temporário isolado, os cenários passaram até `D7` (autenticação, permissões, cadastros e CRUD de brindes) e pararam pela ausência do GD (`imagecreatetruecolor`); a suíte completa não foi validada.
- A validação de produção ainda deve confirmar escrita JSON, abertura em novo request/instância e manutenção de quantidades antes de apagar dados ou iniciar o fluxo TRADE.
