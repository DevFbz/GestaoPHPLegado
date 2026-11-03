# Migração do backend para Laravel

## Estado atual

O backend Laravel agora exige PHP 8.4+ porque o `composer.lock` usa Laravel 13, Symfony 8.1 e `endroid/qr-code` 6.1. O backend legado em `app/` continua sendo o destino do deploy raiz até a validação dos fluxos.

## Problema da implementação anterior

O backend legado executava SQLite em funções serverless e tratava o arquivo local como se fosse persistente. Cada instância podia ver um snapshot diferente; isso explica registros que apareciam e sumiam.

## Persistência JSON atual do app raiz

Por solicitação, o app raiz agora usa documentos JSON privados no Vercel Blob como estado durável. SQLite em `/tmp` é apenas cache de consulta e é reconstruído a partir da última revisão JSON. O manifesto versionado usa ETag/CAS; uma gravação concorrente é recusada com HTTP 409 em vez de sobrescrever silenciosamente outra atualização. Detalhes e limites: [Persistência JSON no Vercel](Persistencia-JSON-Vercel.md).

Esse caminho é uma solução limitada para o app raiz e não equivale a transações multiusuário de um PostgreSQL. Também não conecta automaticamente o frontend à API Laravel separada.

## Arquitetura de longo prazo

- PostgreSQL é a fonte única recomendada para os dados operacionais.
- JSON permanece adequado para fixtures, exportação e armazenamento de arquivos de homologação.
- Cada entrada ou saída usa transação e bloqueio da linha de estoque.
- Cada alteração cria um lançamento imutável em `stock_movements`.
- `idempotency_key` impede duplicidade acidental em reenvios do navegador ou da rede.
- O frontend só será apontado para a API Laravel depois da migração dos fluxos e dos testes.

## Deploy

Para homologação na Vercel, o projeto deve usar `backend` como Root Directory e um PostgreSQL externo. O deploy raiz permanece legado até a conclusão das próximas sprints. Para produção corporativa, recomenda-se hospedar o backend Laravel em um serviço PHP persistente e manter a Vercel para o frontend.

## Validação obrigatória antes da troca

1. executar as migrations em banco vazio;
2. importar uma cópia dos dados antigos;
3. registrar entrada e recarregar a página;
4. registrar saída e recarregar a página;
5. repetir a mesma requisição com a mesma chave de idempotência;
6. testar duas operações simultâneas;
7. conferir saldo, livro e auditoria;
8. só então alterar o roteamento de produção.
