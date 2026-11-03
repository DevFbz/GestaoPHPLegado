# Modo offline

O sistema agora possui a base de funcionamento offline para PC e tablet:

- o aplicativo pode ser instalado como PWA;
- páginas já visitadas e respostas de leitura da API podem ser reutilizadas sem internet;
- operações idempotentes ficam em uma fila local do IndexedDB quando a conexão cai;
- a fila é reenviada automaticamente quando a conexão retorna;
- a API PHP continua sendo a autoridade e grava os dados no banco central;
- Lecom, e-mail e operações que dependem de arquivo continuam exigindo conexão.

O JSON é usado apenas como corpo das requisições. Os dados pendentes são armazenados no IndexedDB, que evita a fragilidade de manter um único arquivo JSON como banco local.

## Operações offline nesta primeira etapa

São enfileiradas as operações com `Idempotency-Key` dos fluxos de solicitações, TRADE, estoque, confirmações e retiradas. Cada operação é reenviada mantendo a mesma chave, evitando duplicidade no servidor. Nas criações de solicitação interna e TRADE, a chave é opcional para preservar compatibilidade com clientes antigos, mas a interface atual sempre a envia.

Operações que exigem Lecom, e-mail, upload ou confirmação externa devem ser feitas quando houver conexão.
