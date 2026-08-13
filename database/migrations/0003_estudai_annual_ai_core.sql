-- EstudAI 0.0.3-alpha - Plano anual inteligente, calendario avancado e IA total
-- Data: 2026-05-17
-- Migration incremental, segura e nao destrutiva.

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(50) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plano_versoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plano_id INT NOT NULL,
  usuario_id INT NOT NULL,
  versao_numero INT NOT NULL,
  motivo VARCHAR(160) NULL,
  tipo_ajuste ENUM('criacao','sem_ajustes','pequenos_ajustes','grandes_ajustes','recriacao') DEFAULT 'criacao',
  plano_json LONGTEXT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_plano_versoes_plano (plano_id),
  INDEX idx_plano_versoes_usuario (usuario_id),
  INDEX idx_plano_versoes_tipo (tipo_ajuste)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revisoes_semanais_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  semana_inicio DATE NOT NULL,
  semana_fim DATE NOT NULL,
  executada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  origem ENUM('cron','manual','fallback') DEFAULT 'cron',
  tarefas_total INT DEFAULT 0,
  tarefas_concluidas INT DEFAULT 0,
  tarefas_atrasadas INT DEFAULT 0,
  percentual_conclusao DECIMAL(5,2) DEFAULT 0,
  minutos_planejados INT DEFAULT 0,
  minutos_concluidos INT DEFAULT 0,
  analise_json LONGTEXT NOT NULL,
  ajuste_tipo ENUM('sem_ajustes','pequenos_ajustes','grandes_ajustes','recriacao') DEFAULT 'sem_ajustes',
  aplicado TINYINT(1) DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  INDEX idx_revisoes_semanais_usuario (usuario_id),
  INDEX idx_revisoes_semanais_semana (semana_inicio),
  INDEX idx_revisoes_semanais_ajuste (ajuste_tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exercicios_planejados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  tarefa_id INT NULL,
  semana_inicio DATE NULL,
  materia VARCHAR(80) NULL,
  conteudo VARCHAR(180) NULL,
  nivel ENUM('facil','medio','dificil','misto') DEFAULT 'misto',
  quantidade INT DEFAULT 0,
  exercicios_json LONGTEXT NOT NULL,
  origem ENUM('ia','fallback','manual') DEFAULT 'ia',
  status ENUM('ativo','arquivado') DEFAULT 'ativo',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  FOREIGN KEY (tarefa_id) REFERENCES tarefas_estudo(id) ON DELETE SET NULL,
  INDEX idx_exercicios_planejados_usuario (usuario_id),
  INDEX idx_exercicios_planejados_tarefa (tarefa_id),
  INDEX idx_exercicios_planejados_semana (semana_inicio),
  INDEX idx_exercicios_planejados_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS respostas_exercicios_planejados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  exercicio_planejado_id INT NOT NULL,
  exercise_key VARCHAR(80) NOT NULL,
  resposta_usuario TEXT NULL,
  resposta_marcada VARCHAR(20) NULL,
  acertou TINYINT(1) NULL,
  avaliacao_json LONGTEXT NULL,
  respondido_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (exercicio_planejado_id) REFERENCES exercicios_planejados(id) ON DELETE CASCADE,
  INDEX idx_respostas_exercicios_usuario (usuario_id),
  INDEX idx_respostas_exercicios_lote (exercicio_planejado_id),
  INDEX idx_respostas_exercicios_acertou (acertou)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simulados_planejados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  tarefa_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  descricao TEXT NULL,
  materia VARCHAR(80) NULL,
  conteudos_json LONGTEXT NULL,
  questoes_json LONGTEXT NULL,
  data_liberacao DATE NOT NULL,
  status ENUM('bloqueado','liberado','iniciado','finalizado','arquivado') DEFAULT 'bloqueado',
  origem ENUM('ia','fallback','manual') DEFAULT 'ia',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  FOREIGN KEY (tarefa_id) REFERENCES tarefas_estudo(id) ON DELETE SET NULL,
  INDEX idx_simulados_planejados_usuario (usuario_id),
  INDEX idx_simulados_planejados_data (data_liberacao),
  INDEX idx_simulados_planejados_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simulados_planejados_respostas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  simulado_planejado_id INT NOT NULL,
  question_key VARCHAR(80) NOT NULL,
  resposta_usuario TEXT NULL,
  resposta_marcada VARCHAR(20) NULL,
  acertou TINYINT(1) NULL,
  avaliacao_json LONGTEXT NULL,
  respondido_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (simulado_planejado_id) REFERENCES simulados_planejados(id) ON DELETE CASCADE,
  INDEX idx_simulados_planejados_resp_usuario (usuario_id),
  INDEX idx_simulados_planejados_resp_simulado (simulado_planejado_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS eventos_calendario_estudai (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  tarefa_id INT NULL,
  tipo ENUM('tarefa','exercicio','revisao','simulado','resumo','meta','ajuste_ia') DEFAULT 'tarefa',
  titulo VARCHAR(160) NOT NULL,
  descricao TEXT NULL,
  data_evento DATE NOT NULL,
  hora_inicio TIME NULL,
  hora_fim TIME NULL,
  status ENUM('pendente','concluido','atrasado','bloqueado','cancelado') DEFAULT 'pendente',
  metadata_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  FOREIGN KEY (tarefa_id) REFERENCES tarefas_estudo(id) ON DELETE SET NULL,
  INDEX idx_eventos_calendario_usuario (usuario_id),
  INDEX idx_eventos_calendario_data (data_evento),
  INDEX idx_eventos_calendario_tipo (tipo),
  INDEX idx_eventos_calendario_status (status)
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS estudai_0003_add_column_if_missing;
DROP PROCEDURE IF EXISTS estudai_0003_add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE estudai_0003_add_column_if_missing(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @estudai_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE estudai_stmt FROM @estudai_sql;
    EXECUTE estudai_stmt;
    DEALLOCATE PREPARE estudai_stmt;
  END IF;
END$$

CREATE PROCEDURE estudai_0003_add_index_if_missing(
  IN p_table VARCHAR(64),
  IN p_index VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index
  ) THEN
    SET @estudai_sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
    PREPARE estudai_stmt FROM @estudai_sql;
    EXECUTE estudai_stmt;
    DEALLOCATE PREPARE estudai_stmt;
  END IF;
END$$

DELIMITER ;

CALL estudai_0003_add_column_if_missing('tarefas_estudo', 'conteudo', '`conteudo` VARCHAR(180) NULL AFTER `materia`');
CALL estudai_0003_add_column_if_missing('tarefas_estudo', 'hora_inicio', '`hora_inicio` TIME NULL AFTER `data_prevista`');
CALL estudai_0003_add_column_if_missing('tarefas_estudo', 'hora_fim', '`hora_fim` TIME NULL AFTER `hora_inicio`');
CALL estudai_0003_add_column_if_missing('tarefas_estudo', 'metadata_json', '`metadata_json` LONGTEXT NULL AFTER `origem`');
CALL estudai_0003_add_column_if_missing('plano_estudo_itens', 'conteudo', '`conteudo` VARCHAR(180) NULL AFTER `materia`');
CALL estudai_0003_add_column_if_missing('plano_estudo_itens', 'hora_inicio', '`hora_inicio` TIME NULL AFTER `data_prevista`');
CALL estudai_0003_add_column_if_missing('plano_estudo_itens', 'hora_fim', '`hora_fim` TIME NULL AFTER `hora_inicio`');
CALL estudai_0003_add_column_if_missing('planos_estudo', 'escopo', '`escopo` ENUM(''semanal'',''anual'') DEFAULT ''semanal'' AFTER `status`');

CALL estudai_0003_add_index_if_missing('tarefas_estudo', 'idx_tarefas_tipo', 'INDEX `idx_tarefas_tipo` (`tipo`)');
CALL estudai_0003_add_index_if_missing('tarefas_estudo', 'idx_tarefas_conteudo', 'INDEX `idx_tarefas_conteudo` (`conteudo`)');

DROP PROCEDURE IF EXISTS estudai_0003_add_index_if_missing;
DROP PROCEDURE IF EXISTS estudai_0003_add_column_if_missing;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('0003_estudai_annual_ai_core', 'Plano anual inteligente, calendario avancado, exercicios e revisao semanal IA');
