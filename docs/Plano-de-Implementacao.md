# Plano de Implementação — Próximas etapas

## Fase 0 — fechamento técnico

- Confirmar domínio, PHP, MySQL/MariaDB e storage persistente.
- Definir mailbox dedicada do Microsoft 365.
- Confirmar processo Lecom publicado, versão e API key de homologação.
- Definir política de logs, backup e retenção.

**Saída:** ambiente de homologação reproduzível e checklist aprovado.

## Fase 1 — e-mail Microsoft 365

- Configurar `MAIL_DRIVER=smtp`, host `smtp.office365.com`, porta 587 e STARTTLS.
- Habilitar `Authenticated SMTP` somente na mailbox do sistema, se a política permitir.
- Configurar `MAIL_FROM_ADDRESS` igual à mailbox ou conceder Send As.
- Configurar cron para `cron/send_notifications.php` a cada minuto.
- Executar o botão de teste de e-mail e confirmar entrega ao solicitante.
- Se SMTP AUTH básico estiver bloqueado, planejar OAuth2 ou Microsoft Graph.

**Saída:** notificação de aprovação, recebimento e retirada entregue ao destinatário.

## Fase 2 — persistência e operação

- Migrar a demo Vercel para MySQL/MariaDB persistente.
- Configurar backup diário, teste de restauração e armazenamento de uploads.
- Configurar monitoramento de `/api/health` e logs de erro.
- Validar perfis com dados reais anonimizados.

**Saída:** ambiente oficial sem perda de dados entre requisições.

## Fase 3 — offline e tablet

- Testar IndexedDB em Chrome/Edge de tablet.
- Definir política para conflitos: duplicidade, estoque alterado e solicitação cancelada.
- Criar tela de pendências com reprocessamento manual.
- Testar PWA, instalação na tela inicial e limpeza de cache.

**Saída:** operação controlada em conexão instável.

## Fase 4 — Lecom em produção

- Validar abertura pelo Workspace com cookie SSO.
- Validar fallback server-to-server com `LECOM_API_KEY` da produção.
- Confirmar permissões do processo e comportamento quando o Lecom estiver indisponível.
- Registrar correlation id interno sem gravar tokens.

**Saída:** uma solicitação TRADE abre o processo correto em produção.

## Fase 5 — qualidade e lançamento

- Rodar lint PHP, testes de cenários, regressões de UI e smoke test de browser.
- Validar importação, backup, PDF, e-mail, Lecom e offline.
- Treinar usuários por perfil.
- Liberar primeiro para grupo piloto e acompanhar erros por uma semana.

## Backlog futuro de IA

- Busca semântica em solicitações e catálogo.
- Resumo de pendências para gestor.
- Classificação de finalidade e sugestão de categoria.
- Previsão de consumo somente após histórico suficiente.

Para esses casos, usar uma API Python separada com fila Celery. LangChain é opcional e deve entrar apenas se simplificar ferramentas, memória ou avaliação. O PHP continua responsável por autorização e escrita transacional.

## Critério de pronto por entrega

Uma fase só avança quando possui documentação atualizada, teste automatizado ou checklist reproduzível, rollback conhecido e evidência de homologação.
