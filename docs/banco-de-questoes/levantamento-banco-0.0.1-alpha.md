# Levantamento de banco - 0.0.1-alpha

Data: 2026-05-15

## Banco atual

Banco identificado: `vestibular_estudos`.

Arquivo principal: `database/schema.sql`.

O schema mistura criacao de tabelas, procedure e seed inicial de questoes, conquistas e simulados.

## Tabelas atuais identificadas

- `usuarios`: dados basicos de conta.
- `questoes`: enunciado, alternativas, resposta correta, explicacao, materia e dificuldade.
- `questoes_do_dia`: questoes geradas por data.
- `respostas_usuario`: resposta marcada, acerto/erro e data.
- `desempenho_diario`: acertos, erros, tempo e finalizacao do dia.
- `preferencias_usuario`: tema, notificacoes e horario de lembrete.
- `metas_semanais`: meta e progresso semanal.
- `conquistas`: catalogo de conquistas.
- `conquistas_usuario`: conquistas desbloqueadas por usuario.
- `simulados`: catalogo de simulados.
- `simulado_questoes`: questoes de cada simulado.
- `simulado_tentativas`: tentativas por usuario.
- `simulado_respostas`: respostas dentro do simulado.
- `anotacoes`: anotacoes por usuario e questao.
- `estatisticas_materia`: tabela prevista para estatisticas por materia.

## Relacoes importantes

- `usuarios.id` e chave estrangeira em preferencias, metas, respostas, desempenho, conquistas, simulados e anotacoes.
- `questoes.id` e chave estrangeira em questoes do dia, respostas, simulados e anotacoes.
- `simulados.id` se relaciona com `simulado_questoes` e `simulado_tentativas`.
- `simulado_tentativas.id` se relaciona com `simulado_respostas`.

## Procedure

- `gerar_questoes_do_dia(p_data DATE)`: cria 10 questoes de matematica e 10 de portugues para a data informada.

## Pontos que podem precisar alteracao

### Mobile

- Persistir checklist de rotina em tabela propria.
- Salvar ultima tela aberta ou preferencias de navegacao mobile.
- Registrar dispositivo/instalacao PWA se notificacoes forem ativadas.

### PWA

- Se houver offline real, sera preciso fila de sincronizacao para respostas.
- Evitar gravar dados conflitantes quando usuario responder offline.

### Planos de estudo

Sugestao de tabelas futuras:

- `planos_estudo`
- `plano_estudo_itens`
- `tarefas_estudo`

Campos recomendados:

- `usuario_id`
- `titulo`
- `tipo`
- `materia`
- `status`
- `data_prevista`
- `concluida_em`
- `ordem`

### Historico de uso

Sugestao:

- `eventos_usuario`

Campos:

- `usuario_id`
- `tipo_evento`
- `origem`
- `metadata_json`
- `criado_em`

### Integracao com IA/OpenRouter

Sugestao:

- `ai_interacoes`

Campos:

- `usuario_id`
- `provider`
- `modelo`
- `tipo`
- `prompt_tokens`
- `completion_tokens`
- `custo_estimado`
- `status`
- `erro`
- `criado_em`

Nao salvar API key no banco.

### Versionamento

Sugestao:

- `schema_migrations`

Campos:

- `version`
- `description`
- `applied_at`

### Preferencias do usuario

Campos possiveis em `preferencias_usuario`:

- `modo_mobile`
- `ultima_secao`
- `pwa_instalado`
- `notificacoes_push`
- `timezone`

### Notificacoes ou tarefas

Sugestao:

- `notificacoes_usuario`
- `tarefas_estudo`

## Migrations sugeridas

Nenhuma alteracao destrutiva deve ser aplicada agora. A etapa 2 adicionou uma migration incremental separada em `database/migrations/0001_estudai_core_alpha.sql`.

Migration segura proposta:

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(50) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

Migration segura proposta para tarefas:

```sql
CREATE TABLE IF NOT EXISTS tarefas_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  tipo ENUM('questoes','revisao','simulado','anotacao','custom') DEFAULT 'custom',
  materia ENUM('matematica','portugues') NULL,
  data_prevista DATE NULL,
  concluida TINYINT(1) DEFAULT 0,
  concluida_em DATETIME NULL,
  criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizada_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

## Riscos

- O schema atual tem seeds e criacao juntos; reexecutar pode duplicar alguns inserts se nao houver controle.
- Alterar enums exige cuidado em MySQL/MariaDB.
- A procedure diaria usa `ORDER BY RAND()`, aceitavel no volume atual, mas pode pesar com muitas questoes.
- Preferencias usam default de tema; nesta versao o default documentado foi alterado para `light`.

## Recomendacao

Criar uma pasta `database/migrations/` antes de qualquer evolucao de schema e manter `schema.sql` como snapshot inicial.
