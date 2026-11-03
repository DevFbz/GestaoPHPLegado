# Base JSON de homologação

Esta pasta documenta a separação da base de dados para testes e homologação. Cada conjunto de informações possui seu próprio arquivo JSON: usuários, perfis, permissões, cadastros, brindes, estoque, movimentações, solicitações, notificações e auditoria.

O sistema continua usando MySQL em produção e SQLite na demonstração da Vercel, porque os fluxos de estoque e aprovação precisam de transações, relacionamentos e bloqueios de linha. O espelho JSON é gerado pelo comando:

```text
php bin/json-db.php export
```

Os arquivos gerados ficam em `storage/json/`, que não é versionado. Para iniciar uma homologação limpa sem apagar o banco relacional:

```text
php bin/json-db.php reset
```

O arquivo `perfis.json` é a fonte legível das regras de menu solicitadas. As senhas nunca são exportadas para JSON.
