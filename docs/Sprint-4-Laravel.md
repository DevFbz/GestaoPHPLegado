# Sprint 4 — Eventos, entregas e protocolos

## Entregue

- eventos planejados, abertos e encerrados;
- cotas de brindes por indústria e evento;
- protocolo persistente de entrega;
- baixa de estoque dentro da mesma transação do protocolo;
- hash da assinatura e hash de verificação;
- idempotência por chave da retirada;
- bloqueio de baixa duplicada após reenvio ou nova leitura do QR;
- URL assinada do QR com validade de sete dias;
- verificação pública limitada a protocolo, tipo e data, sem dados pessoais ou estoque;
- QR visual SVG e PDF de protocolo na API Laravel (`GET /api/v1/deliveries/{id}/qr|pdf`);
- assinatura validada como PNG, persistida no disco privado e incorporada ao PDF quando GD está disponível; sem GD, o PDF registra verificação pelo hash;
- escopo de indústria aplicado à consulta, QR e PDF da entrega;
- endpoints de entregas e eventos na API Laravel.

## Critério de aceite

Uma retirada aprovada gera exatamente um protocolo, uma movimentação por item e o saldo atualizado. Repetir a mesma chave de idempotência retorna o protocolo existente sem nova baixa. QR e PDF retornam artefatos válidos, assinatura persiste fora do diretório público e usuários vinculados a outra indústria não consultam a entrega.

## Integração ainda necessária

A interface do frontend legado ainda precisa consumir os endpoints Laravel e apresentar o QR/PDF na jornada de retirada. O fallback do PDF sem GD inclui o hash verificável, não o desenho da assinatura; para impressão da assinatura visual, o ambiente deve instalar a extensão GD, prevista nos requisitos do produto.