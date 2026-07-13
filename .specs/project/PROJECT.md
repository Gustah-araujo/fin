# Fin — Assistente Financeiro

**Vision:** Um assistente financeiro pessoal com IA que permite casais e indivíduos gerenciarem finanças em workspaces compartilhados, com insights inteligentes e importação automatizada de extratos bancários.

**For:** Indivíduos e casais que querem controle financeiro colaborativo com inteligência artificial acessível.

**Solves:** Desorganização financeira, falta de visibilidade sobre gastos futuros, dificuldade em gerenciar finanças compartilhadas, e tempo perdido categorizando manualmente extratos bancários.

## Goals

- [ ] Workspaces multi-usuário com papéis (admin/editor/viewer) para gestão financeira colaborativa
- [ ] Controle completo de contas, despesas (débito e crédito), receitas, e despesas futuras parceladas
- [ ] Insights financeiros proativos via IA (DeepSeek) com dashboard e chat conversacional
- [ ] Importação inteligente de extratos (CSV/PDF/Excel) com tela de confirmação e detecção de duplicatas
- [ ] Planejamento de gastos futuros para o próximo ciclo de pagamento

## Tech Stack

**Core:**
- Framework: Laravel 13.x
- Language: PHP 8.3+
- Database: MariaDB
- Frontend: React 19 + InertiaJS 2.x
- AI: Laravel AI SDK + DeepSeek (provider-agnostic interface)

**Key dependencies:**
- Laravel Socialite (Google OAuth)
- Laravel AI SDK (agents, tools, streaming)
- InertiaJS (bridge)
- shadcn/ui (React components)

## Scope

**v1 includes:**
- Autenticação (email/senha + Google OAuth)
- Workspaces com papéis (admin, editor, viewer) e convites
- CRUD de contas bancárias (BRL, saldo, tipo)
- CRUD de categorias e tags (por workspace)
- CRUD de despesas em débito (com confirmação de pagamento)
- CRUD de cartões de crédito (data fechamento/vencimento)
- CRUD de despesas de cartão de crédito (compras individuais e parceladas)
- Pagamento de fatura com débito automático da conta selecionada
- CRUD de receitas (com suporte a recorrência)
- CRUD de despesas futuras (dívidas pessoais parceladas, ex: "devo R$1000 em 4x de R$250")
- Dashboard com widgets de resumo financeiro
- Insights IA gerados diariamente (meia-noite) via DeepSeek
- Chat IA conversacional com acesso aos dados financeiros
- Importação de extratos via IA (CSV/PDF/Excel) com tela de confirmação

**Explicitly out of scope:**
- Multi-moeda — apenas BRL
- Notificações push/email — insights visíveis apenas no dashboard
- Mobile app nativo — apenas web responsivo
- Integração bancária direta (Open Banking) — apenas importação de arquivos
- Budget/orçamento planejado vs realizado
- Exportação de dados

## Constraints

- Timeline: Sem prazo fixo
- Technical: Modelo de IA principal DeepSeek (via Laravel AI SDK como provider agnóstico)
- Resources: Desenvolvedor solo
