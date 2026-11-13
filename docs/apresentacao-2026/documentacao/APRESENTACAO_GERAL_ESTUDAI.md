# EstudAI

## Organização, prática e acompanhamento dos estudos

**Documento de apoio ao professor**  
Apresentação: 17/11/2026, Parque Tecnológico de São José dos Campos.  
Referência do conteúdo: versão demonstrativa local examinada em 13/11/2026.

O EstudAI é uma plataforma web que reúne perfil do estudante, planejamento semanal, calendário, atividades de prática e acompanhamento de desempenho. Sua proposta é transformar objetivos e disponibilidade em uma rotina de estudos que possa ser consultada, realizada e acompanhada em um só ambiente.

O foco educacional está na relação entre **planejar, praticar e observar o próprio percurso**. O calendário organiza compromissos; as questões permitem praticar; os resultados ajudam a identificar o que merece atenção. A proposta de inteligência artificial amplia essa organização com análises e recomendações baseadas no perfil.

**O que está disponível hoje:** o fluxo principal foi testado localmente, com contas fictícias e Chrome real. O planejamento demonstrado é gerado por regras locais, identificado como FALLBACK. Questões, práticas vinculadas ao plano, revisões de erros, simulados e consultas de resultados foram validados nos cenários documentados. A integração remota com IA existe, mas ainda não produziu resultados aprovados de ponta a ponta e permanece desativada.

### O problema e sua justificativa

Estudar envolve decisões que um calendário comum não resolve sozinho: quais matérias priorizar, quanto tempo reservar, como distribuir atividades e quando voltar a um assunto difícil. Estudantes também têm horários, objetivos e necessidades diferentes. Uma lista única de compromissos pode registrar datas sem explicar como elas se relacionam com a prática e com os resultados obtidos.

O EstudAI procura aproximar essas partes. A disponibilidade informa o planejamento; as tarefas aparecem no calendário; as práticas registram respostas; os indicadores permitem consultar o desempenho. O problema é apresentado de forma qualitativa: não foram identificados no material analisado estudos com estudantes que comprovem resultados de aprendizagem ou dimensionem estatisticamente essa necessidade.

### Como entender a origem da proposta

A motivação descrita no projeto é reunir organização e acompanhamento, aproveitando informações da rotina para orientar o estudo. O histórico documental mostra a evolução de um ambiente com questões e indicadores para uma proposta que também integra perfil, planejamento e calendário. Não há registro suficiente para atribuir sua criação a entrevistas, a uma pesquisa de campo ou a uma história pessoal específica. Nomes, autoria e contexto institucional devem ser informados pela equipe.

<!-- pagina -->

## 1. Objetivos e público-alvo

### Objetivo geral

Apoiar a organização de uma rotina de estudos compatível com os objetivos, as prioridades e a disponibilidade do estudante, integrando planejamento, prática e acompanhamento de desempenho.

### Objetivos específicos

1. Registrar objetivos, matérias, disponibilidade e necessidades de reforço em um perfil de estudo.
2. Organizar um planejamento semanal que respeite os períodos disponíveis e a data da avaliação, quando informada.
3. Relacionar as atividades planejadas a tarefas e eventos de calendário consultáveis.
4. Oferecer prática com questões e simulados da base previamente cadastrada e aprovada no sistema.
5. Registrar respostas, apresentar correções e permitir a revisão de erros.
6. Exibir histórico e indicadores, distinguindo períodos e modalidades de estudo.
7. Desenvolver o apoio de IA para análises e recomendações, mantendo uma alternativa local de planejamento e validando suas respostas antes do uso.

### Público-alvo

O recorte principal é o estudante do Ensino Médio e quem se prepara para ENEM ou vestibulares. A presença de objetivos de prova, matérias, reforços e redação orientada ao ENEM sustenta esse direcionamento. Pessoas que precisam organizar o estudo de disciplinas também podem se identificar com a proposta.

O formulário possui outras opções de objetivo, mas isso não significa que todos os contextos educacionais tenham sido testados. O projeto não deve ser apresentado como solução já validada para qualquer idade, curso ou necessidade especializada.

### Fluxo de utilização

**1. Entrar:** criar uma conta ou acessar uma conta existente. **2. Informar o perfil:** preencher objetivo, data de prova, dias e blocos de horário. **3. Definir prioridades:** indicar matérias, conteúdos de dificuldade e reforços. **4. Preparar o plano:** obter uma proposta semanal; na demonstração atual, pelo gerador local. **5. Consultar:** visualizar tarefas e calendário. **6. Praticar:** responder questões e abrir atividades vinculadas ao plano. **7. Realizar simulados:** iniciar ou retomar tentativas autorizadas. **8. Acompanhar:** consultar correções, revisões, histórico e indicadores.

A análise remota do perfil e a adaptação automática da rotina fazem parte da proposta de IA, mas não são pré-requisitos aprovados do fluxo local demonstrado. O estudante consegue acessar os recursos recuperados mesmo com essa integração desativada.

<!-- pagina -->

## 2. Planejamento personalizado e inteligência artificial

### O que torna o planejamento personalizado

Personalizar não significa, necessariamente, usar uma IA externa. O planejamento local já utiliza dados do perfil e regras de distribuição. Dias e blocos de horário delimitam quando estudar; matérias e reforços ajudam a selecionar atividades; a data de prova limita o período planejado. O resultado relaciona conteúdo, data, horário, duração, tipo e prioridade da tarefa.

O plano é salvo junto com suas tarefas e eventos. Pedir novamente o plano da mesma semana recupera o registro existente, evitando novas atividades duplicadas. Na demonstração, a indicação **FALLBACK** identifica a geração local alternativa. Ela não é uma resposta comprovada da OpenRouter.

As validações conferem limites de datas, horários, duração e matérias. Essa compatibilidade operacional não equivale a comprovação de eficácia pedagógica nem a garantia de aprovação em uma avaliação.

### A. Proposta de funcionamento da IA

A IA foi projetada como apoio à organização: analisar informações educacionais, produzir um diagnóstico inicial, propor um plano semanal e recomendar ajustes. Dados como objetivos, disponibilidade, dificuldades declaradas, preferências e desempenho podem orientar essas recomendações, conforme o recurso.

Replanejar significa considerar o que foi realizado, o que ficou pendente e uma mudança de rotina para propor uma nova distribuição. Na redação, a proposta é oferecer comentários orientativos por competência e próximos passos de revisão. Não se trata de diagnóstico clínico, correção oficial ou substituição do professor.

### B. O que já foi implementado

Existe integração de backend com a OpenRouter, serviço que intermedeia o acesso a modelos de linguagem. Foram implementados preparação de solicitações, validação de respostas, limites e tratamento de erros. Testes locais simulados verificam contratos de saída e situações de falha. Planejamento semanal e orientação de redação possuem alternativas locais identificadas.

### C. O que ainda precisa de validação

As tentativas reais anteriores de planejamento e diagnóstico não resultaram em saídas aprovadas. A integração remota continua desativada. Replanejamento completo, planejamento anual e aplicação automática da revisão semanal permanecem pendentes ou bloqueados na demonstração. Não se deve anunciar adaptação inteligente automática já operacional.

**Separação essencial:** a IA proposta apoia a organização e as recomendações; questões, alternativas, gabaritos e simulados utilizam a base cadastrada. O sistema não depende de geração autoral de questões por IA em tempo real.

<!-- pagina -->

## 3. Funcionalidades: entrada e organização

Os benefícios abaixo são esperados pela proposta, não resultados medidos de aprendizagem. “Validado” significa que os cenários locais descritos nos relatórios passaram, sem certificar uso em produção ou todas as situações possíveis.

### 3.1 Cadastro e autenticação

**Objetivo:** oferecer acesso individual ao ambiente. **Funcionamento:** cadastro, login, sessão e saída identificam o estudante e restringem seus registros. **Benefício esperado:** continuidade do uso sem misturar resultados entre contas. **Estado:** implementado e validado nos fluxos de autenticação documentados; login real usado nas capturas. Não há validação pública em produção nesta fase.

### 3.2 Onboarding

**Objetivo:** conhecer a rotina antes de planejar. **Funcionamento:** um assistente inicial coleta objetivo, disponibilidade, matérias, reforços e preferências, salvando o perfil. **Benefício esperado:** reduzir a distância entre um plano genérico e o tempo realmente disponível. **Estado:** preenchimento e persistência validados; leitura do perfil e navegação foram demonstradas. Não implica diagnóstico remoto bem-sucedido.

### 3.3 Perfil e identificação de dificuldades

**Objetivo:** registrar prioridades individuais. **Funcionamento:** o estudante informa conteúdos difíceis e necessidades de reforço; respostas e indicadores fornecem sinais adicionais para consulta. **Benefício esperado:** tornar mais claras as escolhas de estudo. **Estado:** perfil e dificuldades declaradas implementados. A identificação automática do nível por IA não está aprovada; não confundir informação declarada com avaliação educacional validada.

### 3.4 Planejamento semanal

**Objetivo:** transformar o perfil em atividades executáveis. **Funcionamento:** distribui tarefas em dias e horários permitidos, com conteúdo, duração e prioridade. **Benefício esperado:** apoiar decisões sobre o que e quando estudar. **Estado:** alternativa local funcional, salva e recuperada com indicação FALLBACK. A geração remota permanece em validação.

### 3.5 Calendário de estudos

**Objetivo:** apresentar os compromissos em uma visão temporal. **Funcionamento:** exibe eventos associados às tarefas, detalhes do dia e visão mensal/anual de eventos existentes. **Benefício esperado:** facilitar a leitura da carga de atividades. **Estado:** consultas e associação com tarefas validadas. A visão anual do calendário não significa geração de um planejamento anual por IA.

<!-- pagina -->

## 4. Funcionalidades: rotina e prática

### 4.1 Tarefas e rotina

**Objetivo:** aproximar o plano das atividades do estudante. **Funcionamento:** apresenta tarefas de hoje, da semana, atrasadas e concluídas recentemente; práticas vinculadas podem concluir os registros relacionados. **Benefício esperado:** tornar visível o andamento da rotina. **Estado:** consultas e conclusões pelos fluxos de prática testados. Edição, adiamento, cancelamento e controle genérico de tempo não devem ser anunciados como liberados apenas porque há botões na interface.

### 4.2 Questões

**Objetivo:** permitir prática com o conteúdo cadastrado. **Funcionamento:** o estudante prepara o treino, consulta enunciado e alternativas, responde e recebe correção; o gabarito é protegido conforme o fluxo. **Benefício esperado:** oferecer retorno sobre cada tentativa. **Estado:** preparação, resposta, finalização e recuperação do histórico validadas com questões existentes. A base é limitada; não há promessa de cobertura completa do ENEM.

### 4.3 Exercícios planejados

**Objetivo:** ligar a atividade prática ao planejamento. **Funcionamento:** uma tarefa abre um conjunto de questões selecionadas da base; respostas e conclusão atualizam os vínculos autorizados. **Benefício esperado:** conectar planejamento e execução. **Estado:** implementado e validado no escopo testado. Quando há questões insuficientes, a redução é informada; um conjunto parcial não deve concluir artificialmente toda a tarefa.

### 4.4 Revisões de erros

**Objetivo:** retomar conteúdos que exigem atenção. **Funcionamento:** consulta erros registrados e permite nova tentativa de revisão, mantendo o resultado diário original separado. **Benefício esperado:** orientar o estudante a revisitar uma dificuldade concreta. **Estado:** fluxo de revisão por erros validado. É diferente da análise e aplicação automática de uma revisão semanal por IA, ainda bloqueada.

### 4.5 Simulados convencionais e planejados

**Objetivo:** reunir questões em uma atividade de avaliação. **Funcionamento:** o convencional parte do catálogo; o planejado está vinculado a uma tarefa. Há retomada, respostas, finalização e consulta do resultado próprio. **Benefício esperado:** oferecer uma visão do desempenho em um conjunto de questões. **Estado:** fluxos validados localmente. Catálogos insuficientes são bloqueados e a correção ocorre no sistema, sem depender de IA externa.

<!-- pagina -->

## 5. Funcionalidades: acompanhamento e apoio

### 5.1 Histórico

**Objetivo:** conservar o percurso de prática. **Funcionamento:** recupera resultados e tentativas próprias já salvos. **Benefício esperado:** permitir que o estudante retome o que fez e consulte resultados anteriores. **Estado:** histórico validado nos fluxos testados, inclusive após nova sessão. Não constitui, por si só, evidência de aprendizagem duradoura.

### 5.2 Estatísticas

**Objetivo:** apresentar informações de desempenho de forma legível. **Funcionamento:** mostra acertos, erros, recortes por matéria/dificuldade, tarefas e evolução por período. **Benefício esperado:** apoiar a interpretação da prática. **Estado:** consultas e cálculos do escopo recuperado validados. Indicadores de períodos ou modalidades diferentes precisam ser explicados antes de serem comparados.

### 5.3 Progresso

**Objetivo:** mostrar o andamento do plano. **Funcionamento:** relaciona atividades concluídas ao total previsto e mantém separados os resultados de questões. **Benefício esperado:** ajudar a perceber o que falta realizar. **Estado:** atualização pelos exercícios e simulados vinculados validada. Conclusão de tarefas é progresso operacional; não equivale a nota de domínio do conteúdo.

### 5.4 Conquistas

**Objetivo:** propor marcos de participação e constância. **Funcionamento:** catálogo de conquistas, com apresentação de nível e pontos. **Benefício esperado:** incentivar continuidade. **Estado:** catálogo consultável e interface existente; a captura mostra zero conquistas desbloqueadas. O desbloqueio automático não foi revalidado para a demonstração e não deve ser prometido como concluído.

### 5.5 Redação

**Objetivo:** apoiar a revisão de um texto do estudante. **Funcionamento:** tema, redação, histórico, checklist e orientação por competências. **Benefício esperado:** organizar a revisão antes de avaliação humana. **Estado:** salvamento e orientação local identificada como fallback validados. Análise remota ainda sem aprovação; o feedback não é nota oficial e não substitui professor ou corretor.

### 5.6 Diagnóstico educacional

**Objetivo:** resumir necessidades e sugerir uma estratégia inicial. **Funcionamento projetado:** análise do perfil para apontar prioridades, rotina e próximos passos. **Benefício esperado:** apoiar uma primeira organização do estudo. **Estado:** estrutura implementada e testada com respostas simuladas; nenhum diagnóstico remoto aprovado está disponível. A captura mostra ausência de diagnóstico, não uma análise produzida por IA.

<!-- pagina -->

## 6. Tecnologias e diferenciais

### Tecnologias efetivamente presentes

| Tecnologia | Papel no EstudAI |
| --- | --- |
| HTML | Estrutura formulários, painéis e conteúdo das páginas. |
| CSS | Define apresentação visual e adaptação do layout a diferentes telas. |
| JavaScript | Controla navegação, interação e carregamento de informações no navegador. Não há React na aplicação examinada. |
| PHP | Processa autenticação, regras, correção, validação e comunicação com o banco. |
| MySQL | Guarda perfis, planos, tarefas, respostas, eventos e resultados. É o banco usado na validação local. |
| Apache / XAMPP | Fornece o ambiente local que serve as páginas e executa o PHP. |
| OpenRouter | Integração prevista para acesso a modelos de IA pelo backend; permanece desativada na demonstração. |
| Recursos PWA | Manifesto e service worker apoiam o comportamento de aplicativo web e o cache de arquivos estáticos. |

Node.js, Python e Chrome foram utilizados em ferramentas de teste e preparação de materiais; não são apresentados como frameworks da aplicação ou como seu backend. O PWA não torna autenticação, banco e IA utilizáveis integralmente sem conexão. A instalação em dispositivo físico e em janela independente ainda exige validação própria.

### Diferenciais da proposta

**Planejamento ligado ao perfil:** a rotina informada participa da organização das atividades, em vez de existir apenas como cadastro.

**Calendário conectado à execução:** plano, tarefa e evento mantêm relação, permitindo consultar o compromisso e abrir uma prática associada.

**Prática e retorno no mesmo ambiente:** questões, revisões e simulados utilizam a base cadastrada e conservam seus resultados.

**Alternativa local ao serviço externo:** o planejamento pode funcionar por regras locais, com origem identificada, sem depender de uma geração remota bem-sucedida.

**Espaço para recomendações futuras:** a arquitetura de integração com IA permite investigar análises do perfil e ajustes de rotina, desde que validados antes da liberação.

Esses pontos expressam como o projeto articula seus recursos. Não constituem comparação com concorrentes, comprovação de pioneirismo ou alegação de superioridade.

<!-- pagina -->

## 7. Potencial educacional, estágio e evolução

### Benefícios esperados

O EstudAI pretende dar mais clareza às prioridades, tornar a rotina consultável, incentivar constância e apoiar a preparação para avaliações. Ao relacionar atividade e resultado, pode favorecer a reflexão do estudante sobre suas escolhas e dificuldades. Esses benefícios são possibilidades da proposta; precisam ser investigados com estudantes e professores.

Não foram apresentados resultados científicos de melhora de notas, redução de evasão, ganho de aprendizagem ou comparação de eficácia. O sistema também não substitui orientação pedagógica. Um professor pode ajudar a interpretar dificuldades e definir atividades adequadas, inclusive quando os indicadores numéricos parecem positivos.

### Estágio atual

Trata-se de um protótipo funcional em versão alfa, demonstrado localmente com dados fictícios. As principais jornadas recuperadas possuem testes documentados de acesso, perfil, planejamento local, consultas, prática, resultados e isolamento entre contas. Houve validação no Chrome real e capturas da interface atual.

As evidências sustentam funcionamento nos cenários testados, não uma certificação para grande escala. Não houve deploy de produção nesta fase. IA remota, automações avançadas e alguns controles exibidos na interface continuam sem liberação. O material de apresentação deve considerar o estado documentado em novembro, e não tomar a lista antiga do README como prova de aprovação de todos os recursos.

### Perspectivas de evolução

1. Concluir a validação remota de diagnóstico e plano, com limites, revisão de qualidade e origem transparente.
2. Recuperar replanejamento e revisão semanal sem perder histórico ou duplicar atividades.
3. Ampliar e revisar a base de questões, informando limites de cobertura.
4. Avaliar o uso com estudantes e professores, reunindo evidências de compreensão, utilidade e dificuldades reais.
5. Validar acessibilidade, instalação PWA e uso em dispositivos e navegadores adicionais.
6. Planejar uma futura publicação com revisão de segurança, privacidade e operação, antes de tratar o sistema como serviço de produção.

Esses são próximos passos sugeridos. Não foram executados nesta entrega nem possuem resultados ou datas de conclusão comprovados.

<!-- pagina -->

## 8. Imagens e interpretação da demonstração

As 12 capturas existentes foram verificadas em `docs/apresentacao-2026/screenshots/`. São imagens reais, com contas fictícias, sem apresentar respostas simuladas como geração remota aprovada. O roteiro de slides indica o uso mais adequado de cada uma.

| Arquivo existente | Uso recomendado |
| --- | --- |
| 01-dashboard.png | Visão geral e acompanhamento da rotina. |
| 02-planejamento.png | Plano semanal, tarefas e origem FALLBACK. |
| 03-calendario.png | Novembro de 2026 e associação com atividades. |
| 04-questoes.png | Enunciado, alternativas e navegação sem correção antecipada. |
| 05-correcao.png | Resposta anterior, explicação e prática concluída. |
| 06-simulados.png | Catálogo e atividade planejada finalizada. |
| 07-resultado-simulado.png | Resultado convencional já persistido. |
| 08-estatisticas.png | Indicadores e evolução, com distinção de períodos. |
| 09-conquistas.png | Interface de conquistas, ainda sem desbloqueios. |
| 10-rotina.png | Estado atual da rotina e atividades concluídas. |
| 11-redacao.png | Texto fictício e orientação local não oficial. |
| 12-perfil-diagnostico.png | Perfil e ausência de diagnóstico remoto aprovado. |

### Os números das imagens não são evidência de eficácia

O plano fictício de 16/11 tem quatro tarefas e duas concluídas: 50% de execução. O painel da semana de 09-15/11 mostra zero tarefas nesse período. Não são percentuais do mesmo intervalo.

O histórico diário de 11/11 tem duas respostas, um acerto e um erro: 50%. A prática planejada exibida possui cinco respostas e quatro acertos: 80%. O simulado convencional tem 1/2 e o planejado anterior tem 9/10. São modalidades distintas. O lote preparado em 13/11 tem 20 questões existentes e nenhuma resposta; não aumenta os acertos históricos.

Conquistas permanecem em zero. Rótulos de interface que mencionam IA não comprovam inferência externa: o serviço remoto ficou desativado. Esses cuidados evitam que o professor atribua à aprendizagem, à mesma semana ou à IA resultados que as telas não demonstram.

<!-- pagina -->

## 9. Referências internas e orientação editorial

Esta documentação foi elaborada com leitura do projeto e dos registros existentes, sem novas chamadas externas, conexão com banco ou alteração do aplicativo. Informações de maturidade seguem os relatórios mais recentes, que delimitam o escopo das listas gerais de funcionalidades.

### Fontes consultadas

- README.md e DOCUMENTACAO.md: definição, arquitetura e proposta funcional.
- docs/auditoria-inicial-0.0.1-alpha.md, docs/versions/0.1.0-alpha.md e documentação de onboarding: evolução e composição da proposta.
- docs/etapa06-gets-com-escrita.md e docs/etapa07-validacao-final.md: contexto das restrições e da validação de acesso/leitura.
- docs/etapa08-planejamento-validacao.md: planejamento local, tarefas, calendário e limites das operações.
- docs/etapa09-validacao-visual-pwa.md: Chrome real e limites do PWA.
- docs/etapa10-questoes-revisoes-progresso.md: questões, correção, histórico e revisões.
- docs/etapa11-exercicios-planejados.md: prática vinculada e conclusão autorizada.
- docs/etapa12-simulados-validacao.md: modalidades, retomada e resultados de simulados.
- docs/etapa13-integracao-ia-validacao.md e docs/etapa13-1-correcao-openrouter.md: integração existente, fallback e validação remota pendente.
- docs/apresentacao-2026/README.md: entrega de capturas da preparação emergencial, contexto dos indicadores e limitações atuais.
- Código e telas de onboarding, planejamento, calendário, questões, estatísticas, conquistas, serviços de IA, manifesto e service worker: confirmação das estruturas efetivamente presentes.

Os registros disponíveis não incluem um relatório individual para cada uma das etapas iniciais 01-05. Também não foi localizado o arquivo ROADMAP.md citado no README. Nenhum desses documentos foi presumido existente. Isso não impede a descrição funcional sustentada pelos arquivos examinados.

### Informações que a equipe deve completar

**Integrantes:** [preencher nomes]. **Professor responsável:** [preencher nome]. **Instituição / curso:** [preencher identificação]. A data e o local do evento foram fornecidos para esta apresentação. A autoria e a instituição não foram inferidas a partir do ambiente técnico.

### Mensagem central para a apresentação

O EstudAI propõe reunir organização, prática e acompanhamento em uma rotina de estudos orientada pelo perfil do estudante. O protótipo já permite demonstrar essa integração com planejamento local e dados fictícios. O apoio remoto de IA é uma frente em validação, que deve evoluir com transparência, segurança e avaliação pedagógica.
