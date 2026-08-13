<?php

function estudaiSystemPrompt(): string {
    return implode("\n", [
        'Voce e um assistente educacional do EstudAI.',
        'EstudAI e uma plataforma inteligente para organizacao, planejamento e acompanhamento da rotina de estudos.',
        'Use IA apenas para analisar rotina, planejar calendario de estudos, revisar a semana e sugerir ajustes de rotina.',
        'Nao gere exercicios, simulados, questoes, gabaritos ou conteudo autoral em tempo real.',
        'Os exercicios do produto vêm de uma base propria revisada, organizada por materia, conteudo e dificuldade.',
        'Quando faltar contexto, peca os dados necessarios.',
        'Use portugues do Brasil e evite respostas excessivamente longas.'
    ]);
}

function estudaiQuestionReviewPrompt(string $enunciado, string $respostaUsuario, string $respostaCorreta): array {
    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt()],
        [
            'role' => 'user',
            'content' => "Explique a questao abaixo.\n\nEnunciado: {$enunciado}\nResposta do estudante: {$respostaUsuario}\nResposta correta: {$respostaCorreta}\n\nMostre o raciocinio e uma dica curta para revisar."
        ]
    ];
}

function estudaiJsonInstruction(): string {
    return 'Responda somente em JSON valido, sem markdown, sem texto fora do objeto JSON.';
}

function estudaiDiagnosticoPrompt(array $perfil): array {
    $perfilJson = json_encode($perfil, JSON_UNESCAPED_UNICODE);

    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Analise o perfil de estudo abaixo e gere um diagnostico inicial educacional, sem prometer avaliacao psicologica, medica ou clinica.\n\nRegras:\n- Retorne somente JSON valido.\n- Nao invente dados que nao estejam no perfil.\n- Respeite a disponibilidade diaria e os dias disponiveis.\n- Considere dificuldades e prioridades.\n- Recomende uma rotina realista e acionavel.\n\nPerfil:\n{$perfilJson}\n\nFormato obrigatorio:\n{\n  \"perfil_resumido\": \"texto curto\",\n  \"principais_dificuldades\": [\"materia ou assunto\"],\n  \"materias_prioritarias\": [\"materia\"],\n  \"estrategia_recomendada\": \"texto curto\",\n  \"rotina_sugerida\": \"texto curto\",\n  \"estrategia_revisao\": \"texto curto\",\n  \"proximos_passos\": [\"passo pratico\"]\n}"
        ]
    ];
}

function estudaiPlanoEstudosPrompt(array $entrada): array {
    return estudaiPlanoSemanalPrompt($entrada);
}

function estudaiPlanoSemanalPrompt(array $entrada): array {
    $entradaJson = json_encode($entrada, JSON_UNESCAPED_UNICODE);

    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Crie um plano semanal de estudos realista com base nos dados estruturados abaixo.\n\nRegras absolutas:\n- Retorne somente JSON valido, sem markdown e sem texto fora do objeto.\n- Use datas YYYY-MM-DD e horarios HH:MM.\n- Copie semana_inicio e semana_fim de janela.semana_inicio e janela.semana_fim.\n- Use apenas datas dentro de janela.semana_inicio e janela.semana_fim.\n- Nunca crie tarefa antes de janela.hoje.\n- Nunca crie tarefa depois de janela.data_limite quando existir.\n- Use apenas os dias listados em janela.dias_cobertura_obrigatoria.\n- Cada data em janela.dias_cobertura_obrigatoria deve ter pelo menos uma tarefa.\n- Use apenas os blocos de horario informados para a propria data em janela.dias_cobertura_obrigatoria[].blocos.\n- Nao invente horarios, nao use horarios fora dos blocos e nao deixe dia obrigatorio sem tarefa.\n- Use apenas materias_base.\n- Se modo_estudo for enem, nao ignore materias obrigatorias; reforco alto aumenta frequencia, mas nao exclui o restante.\n- Tipos permitidos: teoria, questoes, revisao, simulado, resumo, misto.\n- Use questoes para pratica com questoes; nao use exercicios como tipo.\n- Quando usar tipo questoes ou simulado, planeje apenas o momento da pratica; nao crie perguntas, alternativas ou gabaritos.\n- A revisao deve fazer sentido como reforco de algo ja estudado, concluido ou com dificuldade.\n- Conteudo deve ser especifico, curto e coerente com a materia, dificuldades e preferencias do usuario.\n- Se o bloco for curto, uma unica tarefa e aceitavel.\n- Se o bloco for longo, divida em mais de uma atividade apenas quando fizer sentido pedagogico.\n- Nao use sempre 45 minutos por padrao.\n- hora_inicio e hora_fim devem estar dentro da disponibilidade real.\n- tempo_estimado deve ser igual ou muito proximo da diferenca entre hora_inicio e hora_fim.\n- Se entrada.validacao_anterior existir, corrija exatamente os problemas indicados e cubra os dias_sem_tarefas.\n\nEntrada:\n{$entradaJson}\n\nFormato obrigatorio:\n{\n  \"titulo\": \"Plano semanal personalizado\",\n  \"resumo\": \"texto curto\",\n  \"semana_inicio\": \"YYYY-MM-DD\",\n  \"semana_fim\": \"YYYY-MM-DD\",\n  \"estrategia_da_semana\": \"texto curto\",\n  \"tarefas\": [\n    {\n      \"data\": \"YYYY-MM-DD\",\n      \"dia_semana\": \"Segunda-feira\",\n      \"hora_inicio\": \"19:00\",\n      \"hora_fim\": \"20:00\",\n      \"materia\": \"Matematica\",\n      \"conteudo\": \"Funcao do 2 grau\",\n      \"tipo\": \"teoria|questoes|revisao|simulado|resumo|misto\",\n      \"titulo\": \"Estudar funcao do 2 grau\",\n      \"descricao\": \"texto curto\",\n      \"tempo_estimado\": 60,\n      \"prioridade\": \"baixa|media|alta\",\n      \"objetivo\": \"texto curto\"\n    }\n  ],\n  \"observacoes\": [],\n  \"alertas\": []\n}"
        ]
    ];
}

function estudaiPlanoAnualPrompt(array $entrada): array {
    $entradaJson = json_encode($entrada, JSON_UNESCAPED_UNICODE);

    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Crie um plano anual personalizado para o EstudAI.\n\nRegras:\n- Retorne somente JSON valido.\n- Use datas YYYY-MM-DD e horarios HH:MM.\n- Respeite rigorosamente os blocos de horario informados no perfil.\n- Se o objetivo for ENEM, distribua areas e materias de modo coerente.\n- Separe teoria, exercicios, revisao, simulado e resumo.\n- Nao crie tarefas fora da disponibilidade.\n- Nao prometa resultado garantido.\n- Mantenha tarefas pequenas e realistas.\n- Para economizar resposta, gere tarefas detalhadas para as proximas 8 semanas e mantenha meses futuros com objetivos/resumos quando necessario.\n\nEntrada:\n{$entradaJson}\n\nFormato obrigatorio:\n{\n  \"titulo\": \"Plano anual personalizado\",\n  \"resumo\": \"texto curto\",\n  \"data_inicio\": \"YYYY-MM-DD\",\n  \"data_fim\": \"YYYY-MM-DD\",\n  \"estrategia_geral\": \"texto curto\",\n  \"meses\": [\n    {\n      \"mes\": \"YYYY-MM\",\n      \"objetivo_do_mes\": \"texto curto\",\n      \"foco_principal\": [\"materia\"],\n      \"semanas\": [\n        {\n          \"semana_inicio\": \"YYYY-MM-DD\",\n          \"semana_fim\": \"YYYY-MM-DD\",\n          \"objetivo_da_semana\": \"texto curto\",\n          \"tarefas\": [\n            {\n              \"data\": \"YYYY-MM-DD\",\n              \"hora_inicio\": \"19:00\",\n              \"hora_fim\": \"19:40\",\n              \"titulo\": \"texto curto\",\n              \"materia\": \"materia\",\n              \"conteudo\": \"conteudo\",\n              \"tipo\": \"teoria|questoes|revisao|simulado|resumo|misto\",\n              \"descricao\": \"texto curto\",\n              \"tempo_estimado\": 40,\n              \"prioridade\": \"baixa|media|alta\",\n              \"liberar_simulado\": false,\n              \"exercicios_planejados\": true\n            }\n          ]\n        }\n      ]\n    }\n  ],\n  \"regras_de_adaptacao\": [\"regra curta\"],\n  \"observacoes\": [\"observacao curta\"]\n}"
        ]
    ];
}

function estudaiExerciciosPrompt(array $entrada): array {
    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Este fluxo foi desativado: o EstudAI nao gera exercicios por IA em tempo real. Use a base de questoes aprovada do banco."
        ]
    ];
}

function estudaiRevisaoConteudoPrompt(array $entrada): array {
    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Este fluxo foi desativado: o EstudAI nao fabrica revisoes de conteudo por IA em tempo real. Use historico real de erros e questoes aprovadas da base."
        ]
    ];
}

function estudaiSimuladoPlanejadoPrompt(array $entrada): array {
    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Este fluxo foi desativado: o EstudAI nao cria simulados por IA em tempo real. Use questoes aprovadas da base do banco."
        ]
    ];
}

function estudaiRevisaoSemanalPrompt(array $entrada): array {
    $entradaJson = json_encode($entrada, JSON_UNESCAPED_UNICODE);

    return [
        ['role' => 'system', 'content' => estudaiSystemPrompt() . "\n" . estudaiJsonInstruction()],
        [
            'role' => 'user',
            'content' => "Analise a semana do estudante e decida ajustes para a proxima semana.\n\nRegras:\n- Retorne somente JSON valido.\n- Nao altere semanas passadas.\n- Nao altere tarefas concluidas.\n- Nao sugira apagar historico.\n- Considere conclusao, atrasos, acertos, erros, revisoes e simulados.\n- A decisao deve orientar a geracao da proxima semana.\n\nEntrada:\n{$entradaJson}\n\nFormato obrigatorio:\n{\n  \"resumo_semana\": \"texto curto\",\n  \"desempenho\": {\n    \"classificacao\": \"bom|medio|ruim\",\n    \"percentual_conclusao\": 0,\n    \"pontos_fortes\": [],\n    \"pontos_fracos\": []\n  },\n  \"ajuste_pesos\": {\"Matematica\": 3, \"Redacao\": 2},\n  \"decisao\": {\n    \"acao\": \"gerar_proxima_semana|manter|reduzir_carga|aumentar_revisao\",\n    \"motivos\": []\n  },\n  \"mensagem_usuario\": \"texto curto\"\n}"
        ]
    ];
}
