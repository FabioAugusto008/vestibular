# EstudAI: resumo para o professor

**Apresentação:** 17/11/2026, Parque Tecnológico de São José dos Campos.  
**Referência:** estado do protótipo examinado em 13/11/2026.  
**Equipe, professor e instituição:** preencher com as informações oficiais.

## A proposta em uma frase

O EstudAI é uma plataforma web que conecta o perfil do estudante a um planejamento semanal, às atividades de prática e ao acompanhamento dos resultados.

## Problema, objetivo e público

Organizar estudos exige mais do que anotar datas. É necessário escolher prioridades, distribuir o tempo disponível, praticar e voltar aos assuntos que apresentam dificuldade. O projeto procura reunir essas decisões em um ambiente, considerando que cada estudante tem uma rotina diferente.

O objetivo é apoiar uma rotina compatível com metas, matérias e disponibilidade, com foco em estudantes do Ensino Médio e preparação para ENEM ou vestibulares. Os benefícios esperados são maior clareza das prioridades, acompanhamento da rotina e apoio à constância. Não há pesquisa com estudantes que comprove melhora de aprendizagem ou de notas; não usar percentuais de eficácia na apresentação.

## Como o estudante utiliza

1. Cria uma conta ou faz login.
2. Informa objetivo, data de prova, disponibilidade, matérias e reforços no perfil inicial.
3. Obtém um plano semanal, hoje demonstrado pelo gerador local.
4. Consulta tarefas e calendário, abre práticas e realiza simulados.
5. Acompanha respostas, correções, revisões, histórico e indicadores.

As questões e os simulados utilizam a base cadastrada no sistema. Não são fabricados por IA em tempo real. Os registros pertencem à conta do estudante; as demonstrações usam contas e dados fictícios.

## O que está pronto e o que precisa de cuidado

**Validado no escopo local:** autenticação, perfil inicial, consultas de tarefas e calendário, prática com questões, revisão de erros, exercícios ligados ao plano, simulados e recuperação de resultados. Essas jornadas foram testadas, incluindo uso no Chrome real.

**Funciona com alternativa local:** planejamento semanal e orientação de redação. A palavra FALLBACK significa que o resultado veio de regras locais alternativas, e não de uma geração remota aprovada. A orientação de redação é não oficial e não substitui avaliação humana.

**Em validação ou bloqueado:** diagnóstico remoto, geração de plano por IA, replanejamento completo, planejamento anual e aplicação automática da revisão semanal. A OpenRouter está integrada ao backend, mas as saídas reais anteriores não foram aprovadas; a IA remota permanece desativada.

**Limites de demonstração:** o catálogo de conquistas pode ser mostrado, mas o desbloqueio automático não foi revalidado e as capturas têm zero conquistas. Botões visíveis não comprovam liberação de todas as ações. O PWA conserva arquivos estáticos, mas não torna todo o sistema utilizável sem conexão. Não houve publicação em produção.

## Como apresentar o papel da IA

“A proposta é usar inteligência artificial para apoiar a análise do perfil e recomendar uma organização de estudos. O protótipo atual já funciona com planejamento local, enquanto a integração remota continua em validação.”

Evitar frases como “a IA já identifica automaticamente o nível de cada aluno” ou “o sistema se adapta sozinho a qualquer dificuldade”. Essas capacidades não foram comprovadas na versão apresentada.

## Tecnologias e diferenciais

HTML, CSS e JavaScript compõem a interface; PHP processa as regras; MySQL armazena os dados; Apache fornece o ambiente local. Recursos PWA apoiam o comportamento de aplicativo web. OpenRouter é a integração prevista para modelos de IA. Não apresentar React ou Python como frameworks da aplicação.

O principal diferencial da proposta é a conexão entre perfil, plano, calendário, prática e acompanhamento. A personalização local e a identificação transparente da origem permitem demonstrar o núcleo sem depender de um serviço externo de IA.

## Organização dos slides e das imagens

O roteiro anexo propõe **12 slides**: capa, problema, proposta, fluxo, perfil, personalização/IA, planejamento/calendário, prática/simulados, acompanhamento, tecnologias, estágio/evolução e conclusão. Cada slide inclui texto pronto, objetivo, imagem sugerida e notas de fala.

As 12 capturas verificadas estão em `../screenshots/`, com ZIP em `../screenshots.zip`. Priorizar dashboard, plano, calendário, questões, simulados e estatísticas. Login, disponibilidade do onboarding e reforços ainda precisam de novas capturas específicas nessa pasta; elas não foram produzidas nesta entrega. O roteiro oferece alternativas com imagens existentes.

**Atenção aos números:** 50% do plano representa duas de quatro tarefas de 16/11. O zero semanal refere-se a 09-15/11. O resultado diário de 50% vem de duas respostas em 11/11; a prática planejada e os simulados são modalidades distintas. Esses dados são exemplos fictícios de funcionamento, não resultados de eficácia educacional.

## Próximos passos e conclusão

Validar a IA remota e a qualidade das recomendações, ampliar a base de questões, recuperar automações com segurança e avaliar utilidade e acessibilidade com estudantes e professores. A mensagem final é uma proposta de organização integrada, com núcleo demonstrável e evolução responsável, sem apresentar capacidades futuras como concluídas.
