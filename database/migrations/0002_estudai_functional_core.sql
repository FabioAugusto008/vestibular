-- EstudAI 0.0.2-alpha - Nucleo funcional
-- Data: 2026-05-17
-- Migration incremental, segura e nao destrutiva.
-- Nao apaga dados e nao depende de reexecutar database/schema.sql.

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(50) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estudo_perfis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  objetivo VARCHAR(120) NOT NULL,
  data_prova DATE NULL,
  horas_dia DECIMAL(4,2) NULL,
  dias_semana_json LONGTEXT NULL,
  dificuldades_json LONGTEXT NULL,
  prioridades_json LONGTEXT NULL,
  preferencia_estudo VARCHAR(80) NULL,
  meta_semanal VARCHAR(120) NULL,
  notificacoes TINYINT(1) DEFAULT 0,
  perfil_json LONGTEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_estudo_perfis_usuario (usuario_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS onboarding_respostas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  versao VARCHAR(30) NOT NULL,
  respostas_json LONGTEXT NOT NULL,
  concluido_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_onboarding_usuario (usuario_id),
  INDEX idx_onboarding_versao (versao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planos_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  perfil_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  origem ENUM('ia','fallback','manual') DEFAULT 'ia',
  status ENUM('ativo','arquivado','substituido') DEFAULT 'ativo',
  resumo TEXT NULL,
  data_inicio DATE NULL,
  data_fim DATE NULL,
  plano_json LONGTEXT NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (perfil_id) REFERENCES estudo_perfis(id) ON DELETE SET NULL,
  INDEX idx_planos_usuario (usuario_id),
  INDEX idx_planos_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plano_estudo_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plano_id INT NOT NULL,
  usuario_id INT NOT NULL,
  dia_semana VARCHAR(30) NULL,
  data_prevista DATE NULL,
  materia VARCHAR(80) NULL,
  tipo_atividade ENUM('teoria','questoes','revisao','simulado','resumo','misto','custom') DEFAULT 'custom',
  titulo VARCHAR(160) NOT NULL,
  descricao TEXT NULL,
  tempo_estimado INT NULL,
  status ENUM('pendente','concluida','atrasada','remarcada','cancelada') DEFAULT 'pendente',
  ordem INT DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_itens_plano (plano_id),
  INDEX idx_itens_usuario (usuario_id),
  INDEX idx_itens_data_prevista (data_prevista),
  INDEX idx_itens_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarefas_estudo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  item_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  descricao TEXT NULL,
  materia VARCHAR(80) NULL,
  tipo ENUM('teoria','questoes','revisao','simulado','resumo','misto','custom') DEFAULT 'custom',
  data_prevista DATE NULL,
  tempo_estimado INT NULL,
  prioridade ENUM('baixa','media','alta') DEFAULT 'media',
  status ENUM('pendente','concluida','atrasada','remarcada','cancelada') DEFAULT 'pendente',
  origem ENUM('ia','fallback','manual','reajuste') DEFAULT 'ia',
  concluida_em DATETIME NULL,
  criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizada_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  FOREIGN KEY (item_id) REFERENCES plano_estudo_itens(id) ON DELETE SET NULL,
  INDEX idx_tarefas_usuario (usuario_id),
  INDEX idx_tarefas_data_prevista (data_prevista),
  INDEX idx_tarefas_status (status),
  INDEX idx_tarefas_plano (plano_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ia_historico (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  provider VARCHAR(60) DEFAULT 'openrouter',
  modelo VARCHAR(120) NULL,
  tipo VARCHAR(80) NOT NULL,
  entrada_resumo TEXT NULL,
  resposta_json LONGTEXT NULL,
  status ENUM('sucesso','erro','fallback') DEFAULT 'sucesso',
  erro TEXT NULL,
  tokens_entrada INT NULL,
  tokens_saida INT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_ia_usuario (usuario_id),
  INDEX idx_ia_tipo (tipo),
  INDEX idx_ia_status (status)
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS estudai_add_fk_if_missing;
DROP PROCEDURE IF EXISTS estudai_replace_fk_on_column;
DROP PROCEDURE IF EXISTS estudai_add_index_if_missing;
DROP PROCEDURE IF EXISTS estudai_add_column_if_missing;

DELIMITER $$

CREATE PROCEDURE estudai_add_column_if_missing(
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

CREATE PROCEDURE estudai_add_index_if_missing(
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

CREATE PROCEDURE estudai_add_fk_if_missing(
  IN p_table VARCHAR(64),
  IN p_constraint VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND CONSTRAINT_NAME = p_constraint
  ) THEN
    SET @estudai_sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
    PREPARE estudai_stmt FROM @estudai_sql;
    EXECUTE estudai_stmt;
    DEALLOCATE PREPARE estudai_stmt;
  END IF;
END$$

CREATE PROCEDURE estudai_replace_fk_on_column(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_constraint VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  DECLARE v_constraint VARCHAR(64);

  SELECT CONSTRAINT_NAME INTO v_constraint
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = p_table
    AND COLUMN_NAME = p_column
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1;

  IF v_constraint IS NOT NULL THEN
    SET @estudai_sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', v_constraint, '`');
    PREPARE estudai_stmt FROM @estudai_sql;
    EXECUTE estudai_stmt;
    DEALLOCATE PREPARE estudai_stmt;
  END IF;

  SET @estudai_sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
  PREPARE estudai_stmt FROM @estudai_sql;
  EXECUTE estudai_stmt;
  DEALLOCATE PREPARE estudai_stmt;
END$$

DELIMITER ;

CALL estudai_add_column_if_missing('estudo_perfis', 'perfil_json', '`perfil_json` LONGTEXT NULL AFTER `notificacoes`');
ALTER TABLE estudo_perfis MODIFY COLUMN objetivo VARCHAR(120) NOT NULL;
ALTER TABLE estudo_perfis MODIFY COLUMN preferencia_estudo VARCHAR(80) NULL;
ALTER TABLE estudo_perfis MODIFY COLUMN meta_semanal VARCHAR(120) NULL;
CALL estudai_add_index_if_missing('estudo_perfis', 'uq_estudo_perfis_usuario', 'UNIQUE KEY `uq_estudo_perfis_usuario` (`usuario_id`)');

ALTER TABLE onboarding_respostas MODIFY COLUMN versao VARCHAR(30) NOT NULL;
ALTER TABLE onboarding_respostas MODIFY COLUMN respostas_json LONGTEXT NOT NULL;
CALL estudai_add_index_if_missing('onboarding_respostas', 'idx_onboarding_usuario', 'INDEX `idx_onboarding_usuario` (`usuario_id`)');
CALL estudai_add_index_if_missing('onboarding_respostas', 'idx_onboarding_versao', 'INDEX `idx_onboarding_versao` (`versao`)');

CALL estudai_add_column_if_missing('planos_estudo', 'perfil_id', '`perfil_id` INT NULL AFTER `usuario_id`');
ALTER TABLE planos_estudo MODIFY COLUMN origem ENUM('ia','fallback','manual') DEFAULT 'ia';
ALTER TABLE planos_estudo MODIFY COLUMN status ENUM('rascunho','ativo','pausado','concluido','arquivado','substituido') DEFAULT 'ativo';
ALTER TABLE planos_estudo MODIFY COLUMN plano_json LONGTEXT NULL;
CALL estudai_add_index_if_missing('planos_estudo', 'idx_planos_usuario', 'INDEX `idx_planos_usuario` (`usuario_id`)');
CALL estudai_add_index_if_missing('planos_estudo', 'idx_planos_status', 'INDEX `idx_planos_status` (`status`)');
CALL estudai_add_fk_if_missing('planos_estudo', 'fk_planos_estudo_perfil', 'FOREIGN KEY (`perfil_id`) REFERENCES `estudo_perfis`(`id`) ON DELETE SET NULL');

CALL estudai_add_column_if_missing('plano_estudo_itens', 'titulo', '`titulo` VARCHAR(160) NOT NULL DEFAULT ''Bloco de estudo'' AFTER `tipo_atividade`');
CALL estudai_add_column_if_missing('plano_estudo_itens', 'tempo_estimado', '`tempo_estimado` INT NULL AFTER `descricao`');
CALL estudai_add_column_if_missing('plano_estudo_itens', 'atualizado_em', '`atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `criado_em`');
ALTER TABLE plano_estudo_itens MODIFY COLUMN dia_semana VARCHAR(30) NULL;
ALTER TABLE plano_estudo_itens MODIFY COLUMN tipo_atividade ENUM('teoria','questoes','revisao','simulado','resumo','misto','custom','outro') DEFAULT 'custom';
ALTER TABLE plano_estudo_itens MODIFY COLUMN status ENUM('pendente','em_andamento','concluido','concluida','adiado','atrasada','remarcada','cancelado','cancelada') DEFAULT 'pendente';
CALL estudai_add_index_if_missing('plano_estudo_itens', 'idx_itens_plano', 'INDEX `idx_itens_plano` (`plano_id`)');
CALL estudai_add_index_if_missing('plano_estudo_itens', 'idx_itens_usuario', 'INDEX `idx_itens_usuario` (`usuario_id`)');
CALL estudai_add_index_if_missing('plano_estudo_itens', 'idx_itens_data_prevista', 'INDEX `idx_itens_data_prevista` (`data_prevista`)');
CALL estudai_add_index_if_missing('plano_estudo_itens', 'idx_itens_status', 'INDEX `idx_itens_status` (`status`)');

CALL estudai_add_column_if_missing('tarefas_estudo', 'item_id', '`item_id` INT NULL AFTER `plano_id`');
CALL estudai_add_column_if_missing('tarefas_estudo', 'descricao', '`descricao` TEXT NULL AFTER `titulo`');
CALL estudai_add_column_if_missing('tarefas_estudo', 'tempo_estimado', '`tempo_estimado` INT NULL AFTER `data_prevista`');
CALL estudai_add_column_if_missing('tarefas_estudo', 'prioridade', '`prioridade` ENUM(''baixa'',''media'',''alta'') DEFAULT ''media'' AFTER `tempo_estimado`');
CALL estudai_add_column_if_missing('tarefas_estudo', 'origem', '`origem` ENUM(''ia'',''fallback'',''manual'',''reajuste'') DEFAULT ''ia'' AFTER `status`');
ALTER TABLE tarefas_estudo MODIFY COLUMN tipo ENUM('teoria','questoes','revisao','simulado','resumo','misto','anotacao','custom') DEFAULT 'custom';
ALTER TABLE tarefas_estudo MODIFY COLUMN status ENUM('pendente','em_andamento','concluida','adiada','atrasada','remarcada','cancelada') DEFAULT 'pendente';
CALL estudai_add_index_if_missing('tarefas_estudo', 'idx_tarefas_usuario', 'INDEX `idx_tarefas_usuario` (`usuario_id`)');
CALL estudai_add_index_if_missing('tarefas_estudo', 'idx_tarefas_data_prevista', 'INDEX `idx_tarefas_data_prevista` (`data_prevista`)');
CALL estudai_add_index_if_missing('tarefas_estudo', 'idx_tarefas_status', 'INDEX `idx_tarefas_status` (`status`)');
CALL estudai_add_index_if_missing('tarefas_estudo', 'idx_tarefas_plano', 'INDEX `idx_tarefas_plano` (`plano_id`)');
CALL estudai_add_fk_if_missing('tarefas_estudo', 'fk_tarefas_item', 'FOREIGN KEY (`item_id`) REFERENCES `plano_estudo_itens`(`id`) ON DELETE SET NULL');

UPDATE ia_historico SET tipo = 'outro' WHERE tipo IS NULL OR tipo = '';
ALTER TABLE ia_historico MODIFY COLUMN usuario_id INT NULL;
ALTER TABLE ia_historico MODIFY COLUMN provider VARCHAR(60) DEFAULT 'openrouter';
ALTER TABLE ia_historico MODIFY COLUMN tipo VARCHAR(80) NOT NULL;
ALTER TABLE ia_historico MODIFY COLUMN resposta_json LONGTEXT NULL;
CALL estudai_add_column_if_missing('ia_historico', 'tokens_entrada', '`tokens_entrada` INT NULL AFTER `erro`');
CALL estudai_add_column_if_missing('ia_historico', 'tokens_saida', '`tokens_saida` INT NULL AFTER `tokens_entrada`');
CALL estudai_add_index_if_missing('ia_historico', 'idx_ia_usuario', 'INDEX `idx_ia_usuario` (`usuario_id`)');
CALL estudai_add_index_if_missing('ia_historico', 'idx_ia_tipo', 'INDEX `idx_ia_tipo` (`tipo`)');
CALL estudai_add_index_if_missing('ia_historico', 'idx_ia_status', 'INDEX `idx_ia_status` (`status`)');
CALL estudai_replace_fk_on_column('ia_historico', 'usuario_id', 'fk_ia_historico_usuario', 'FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL');

DROP PROCEDURE IF EXISTS estudai_add_fk_if_missing;
DROP PROCEDURE IF EXISTS estudai_replace_fk_on_column;
DROP PROCEDURE IF EXISTS estudai_add_index_if_missing;
DROP PROCEDURE IF EXISTS estudai_add_column_if_missing;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('0002_estudai_functional_core', 'Nucleo funcional EstudAI 0.0.2-alpha');
