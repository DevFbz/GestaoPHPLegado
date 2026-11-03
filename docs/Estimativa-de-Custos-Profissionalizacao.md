# Estimativa de custos para profissionalização do projeto

**Projeto:** Controle de Brindes (Gbrindes)  
**Referência de preços:** 23 de outubro de 2026  
**Objetivo:** estimativa inicial para planejamento; não é cotação comercial, orçamento fechado ou parecer jurídico.

## Resumo executivo

| Item | Estimativa inicial |
|---|---:|
| Certificado digital ICP-Brasil e-CNPJ A1, 1 ano | R$ 203–275 |
| Registro do programa de computador no INPI | R$ 210 por pedido |
| Registro de marca, 1 classe, especificação pré-aprovada | R$ 880; R$ 440 com desconto, se elegível |
| Infraestrutura enxuta estimada | US$ 57/mês, aproximadamente R$ 288/mês |
| Pentest de aplicação web | R$ 18.000–35.000 para escopo web; R$ 30.000–55.000 para incluir lógica de negócio e autenticação |
| Total aproximado no primeiro ano | R$ 22.700–59.800, conforme profundidade do pentest |

O total do primeiro ano considera uma estimativa básica de hospedagem por 12 meses, certificado e-CNPJ, um pedido de registro de software, uma marca em uma classe e o pentest. Não inclui serviços jurídicos, correções após o pentest, impostos/tarifas cambiais, domínio, custos de alta disponibilidade ou certificações ISO/SOC 2.

## 1. Certificado digital

Não existe um certificado ICP-Brasil que certifique que o software é seguro ou que está em conformidade com a LGPD. O certificado identifica a pessoa ou empresa que assina digitalmente. Para o pedido de registro do software no INPI, é exigido certificado digital qualificado ICP-Brasil.

Como referência de mercado, preços publicados para e-CNPJ A1 com validade de um ano ficaram em torno de **R$ 203 a R$ 275**. O valor varia conforme a certificadora, promoção e validação. O e-CPF do representante pode ser adequado em determinados casos; confirmar qual titular/certificado será aceito para a conta e o pedido no e-INPI antes de comprar. Se a empresa ou representante já tiver um certificado válido compatível, talvez não haja custo adicional.

O certificado A1 é renovável; portanto, incluir a renovação no orçamento anual seguinte.

## 2. Registro no INPI

### Programa de computador

- Taxa oficial do pedido eletrônico de registro de programa de computador (código 730): **R$ 210**.
- O INPI informa que não há desconto para serviços relativos a programa de computador.
- O pedido exige certificado digital qualificado ICP-Brasil.
- O registro do software é diferente do registro do nome comercial ou logotipo.

### Marca

Para um pedido eletrônico de marca, por classe:

- Especificação pré-aprovada: **R$ 880** sem desconto ou **R$ 440** com desconto, quando o titular se enquadrar nas regras vigentes.
- Especificação de livre preenchimento: **R$ 1.720** sem desconto ou **R$ 860** com desconto.

O preço é por classe. O nome e o logotipo podem demandar pedidos distintos dependendo da estratégia de proteção; pesquisar a disponibilidade e confirmar a classificação antes de protocolar. Os valores não incluem eventual contratação de advogado ou agente de propriedade industrial.

## 3. Pentest e contratação da empresa

Referências comerciais brasileiras consultadas para 2026 indicam aproximadamente:

- **R$ 18.000–35.000** para pentest de aplicação web com escopo OWASP;
- **R$ 30.000–55.000** para escopo mais profundo, incluindo autenticação e lógica de negócio;
- testes específicos de API podem ser orçados separadamente ou incluídos no escopo geral.

Para o Controle de Brindes, solicitar proposta que inclua aplicação web e API, autenticação, perfis/permissões, isolamento entre indústrias, aprovações e fluxos de entrega. A faixa mais completa é a referência prudente para o sistema; o preço final depende do número de endpoints, perfis, ambientes, profundidade manual e reteste.

O contrato/ordem de serviço deve deixar explícitos:

- autorização formal para testar os domínios, APIs, IPs e ambientes indicados;
- escopo, técnicas permitidas, datas, horários e contas de teste;
- limites para evitar indisponibilidade, alteração ou extração de dados reais;
- confidencialidade, tratamento e descarte das evidências coletadas;
- comunicação imediata de vulnerabilidades críticas;
- relatório técnico e executivo com evidências, severidade e recomendações;
- prazo e condições para reteste das correções;
- responsabilidades, contatos de emergência e regras para envolver provedores terceiros.

Solicitar pelo menos três propostas com o mesmo escopo para comparar. Uma varredura automática, isoladamente, não equivale a um pentest manual de regras de negócio.

## 4. Hospedagem e infraestrutura

Estimativa básica baseada na arquitetura considerada para o projeto: frontend na Vercel, PostgreSQL no Supabase e backend Laravel em uma VM:

| Componente | Plano usado na estimativa | Preço de referência |
|---|---|---:|
| Frontend/deploy | Vercel Pro, tarifa base | US$ 20/mês |
| Banco PostgreSQL | Supabase Pro, projeto inicial | US$ 25/mês |
| Backend Laravel | DigitalOcean Basic Droplet, 2 GiB RAM, 1 vCPU | US$ 12/mês |
| **Total-base** |  | **US$ 57/mês** |

Usando **R$ 5,049 por US$ 1** como referência indicativa próxima ao fechamento de 22/10/2026, o custo-base fica em cerca de **R$ 287,79/mês** ou **R$ 3.453,52/ano**. O valor em reais pode variar com câmbio, IOF, tarifa do cartão, consumo e impostos aplicáveis.

Esse é um ponto de partida enxuto, não uma arquitetura de alta disponibilidade. A VM exige administração e atualização. O valor não inclui domínio, e-mail corporativo, backups adicionais, armazenamento de arquivos, monitoramento, ambiente separado de homologação, suporte gerenciado ou redundância. O Supabase Pro inclui limites e backups conforme o plano, mas serviços e consumo adicionais podem aumentar a fatura.

## 5. Cenários de orçamento no primeiro ano

Premissas comuns: certificado e-CNPJ A1 de R$ 203–275; registro do software de R$ 210; uma marca em uma classe por R$ 880; hospedagem-base de R$ 3.454 por ano. Para a faixa com desconto da marca, subtrair R$ 440 se a empresa for elegível.

| Cenário | Pentest | Total aproximado no primeiro ano |
|---|---:|---:|
| Pentest web com escopo OWASP | R$ 18.000–35.000 | **R$ 22.700–39.800** |
| Pentest mais completo, com autenticação e lógica de negócio | R$ 30.000–55.000 | **R$ 34.700–59.800** |

Os totais são arredondados para planejamento. Não incluem correções técnicas, assessoria jurídica/LGPD, preparação de documentação, contratação de agente de PI, impostos/tarifas cambiais, disponibilidade adicional nem eventual certificação ISO 27001/SOC 2.

## Fontes consultadas

- [INPI — Registro de programa de computador: requisitos e taxa do código 730](https://www.gov.br/pt-br/servicos/solicitar-o-registro-de-programa-de-computador?id=2878&origem=servico)
- [INPI — Pedido de registro de marca e taxas por classe](https://www.gov.br/pt-br/servicos/solicitar-o-registro-de-marca-de-produto-ou-servico?id=13605&origem=servico)
- [INPI — Tabela de retribuições, serviços relativos a marcas](https://www.gov.br/inpi/pt-br/inpi-data/precificacao-dos-servicos/NovaTabeladeRetribuiesINPI_PortariaINPI_Final_20_dez_25.pdf)
- [ITI — Perguntas frequentes sobre certificados ICP-Brasil](https://www.gov.br/iti/pt-br/acesso-a-informacao/perguntas-frequentes/certificacao-digital)
- [Pesquisa de preços de e-CNPJ A1 em outubro de 2026](https://blog.viabusiness.com.br/quanto-custa-certificado-digital-e-cnpj/)
- [Vercel — Planos e preços](https://vercel.com/pricing)
- [Supabase — Planos e preços](https://supabase.com/pricing)
- [DigitalOcean — Preços de Droplets](https://www.digitalocean.com/pricing/droplets)
- [Pentest — referência de preços no Brasil em 2026](https://www.decripte.com.br/guias-de-compra/quanto-custa-um-pentest)
- [Histórico USD/BRL usado apenas como referência de conversão](https://calculatorlib.com/currency-converter/usd-to-brl/history)
