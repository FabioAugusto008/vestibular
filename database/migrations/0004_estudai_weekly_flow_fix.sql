-- EstudAI 0.0.4-alpha - Fluxo semanal inteligente e correcao de logica
-- Data: 2026-05-17
-- Migration incremental, segura e nao destrutiva.

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(80) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS estudai_0004_add_column_if_missing;
DROP PROCEDURE IF EXISTS estudai_0004_add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE estudai_0004_add_column_if_missing(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
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

CREATE PROCEDURE estudai_0004_add_index_if_missing(
  IN p_table VARCHAR(64),
  IN p_index VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
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

CALL estudai_0004_add_column_if_missing('estudo_perfis', 'onboarding_completo', '`onboarding_completo` TINYINT(1) DEFAULT 0 AFTER `usuario_id`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'modo_estudo', '`modo_estudo` VARCHAR(40) NULL AFTER `objetivo`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'lingua_estrangeira', '`lingua_estrangeira` VARCHAR(20) NULL AFTER `modo_estudo`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'disponibilidade_json', '`disponibilidade_json` LONGTEXT NULL AFTER `notificacoes`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'reforcos_json', '`reforcos_json` LONGTEXT NULL AFTER `disponibilidade_json`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'materias_base_json', '`materias_base_json` LONGTEXT NULL AFTER `reforcos_json`');
CALL estudai_0004_add_column_if_missing('estudo_perfis', 'atualizado_em', '`atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL estudai_0004_add_column_if_missing('planos_estudo', 'tipo_plano', '`tipo_plano` ENUM(''semanal'',''anual'',''manual'') DEFAULT ''semanal'' AFTER `status`');
CALL estudai_0004_add_column_if_missing('planos_estudo', 'semana_inicio', '`semana_inicio` DATE NULL AFTER `data_inicio`');
CALL estudai_0004_add_column_if_missing('planos_estudo', 'semana_fim', '`semana_fim` DATE NULL AFTER `semana_inicio`');
CALL estudai_0004_add_column_if_missing('planos_estudo', 'valido_de', '`valido_de` DATE NULL AFTER `semana_fim`');
CALL estudai_0004_add_column_if_missing('planos_estudo', 'valido_ate', '`valido_ate` DATE NULL AFTER `valido_de`');
CALL estudai_0004_add_index_if_missing('planos_estudo', 'idx_planos_tipo_plano', 'INDEX `idx_planos_tipo_plano` (`tipo_plano`)');
CALL estudai_0004_add_index_if_missing('planos_estudo', 'idx_planos_semana', 'INDEX `idx_planos_semana` (`semana_inicio`, `semana_fim`)');

CALL estudai_0004_add_column_if_missing('tarefas_estudo', 'conteudo', '`conteudo` VARCHAR(180) NULL AFTER `materia`');
CALL estudai_0004_add_column_if_missing('tarefas_estudo', 'hora_inicio', '`hora_inicio` TIME NULL AFTER `data_prevista`');
CALL estudai_0004_add_column_if_missing('tarefas_estudo', 'hora_fim', '`hora_fim` TIME NULL AFTER `hora_inicio`');
CALL estudai_0004_add_column_if_missing('tarefas_estudo', 'fonte_conteudo', '`fonte_conteudo` ENUM(''plano'',''ia'',''manual'',''fallback'') DEFAULT ''plano'' AFTER `origem`');
CALL estudai_0004_add_index_if_missing('tarefas_estudo', 'idx_tarefas_horario', 'INDEX `idx_tarefas_horario` (`data_prevista`, `hora_inicio`)');

CALL estudai_0004_add_column_if_missing('eventos_calendario_estudai', 'conteudo', '`conteudo` VARCHAR(180) NULL AFTER `descricao`');
CALL estudai_0004_add_column_if_missing('eventos_calendario_estudai', 'semana_inicio', '`semana_inicio` DATE NULL AFTER `hora_fim`');
CALL estudai_0004_add_column_if_missing('eventos_calendario_estudai', 'semana_fim', '`semana_fim` DATE NULL AFTER `semana_inicio`');
CALL estudai_0004_add_index_if_missing('eventos_calendario_estudai', 'idx_eventos_semana', 'INDEX `idx_eventos_semana` (`semana_inicio`, `semana_fim`)');

CREATE TABLE IF NOT EXISTS planejamento_semanal_controle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NULL,
  semana_inicio DATE NOT NULL,
  semana_fim DATE NOT NULL,
  status ENUM('ativo','encerrado','substituido') DEFAULT 'ativo',
  origem ENUM('ia','fallback','manual','revisao_domingo') DEFAULT 'ia',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  INDEX idx_planejamento_usuario (usuario_id),
  INDEX idx_planejamento_inicio (semana_inicio),
  INDEX idx_planejamento_fim (semana_fim),
  INDEX idx_planejamento_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revisoes_conteudo_ia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tarefa_id INT NULL,
  plano_id INT NULL,
  materia VARCHAR(80) NOT NULL,
  conteudo VARCHAR(180) NOT NULL,
  revisao_json LONGTEXT NOT NULL,
  origem ENUM('ia','fallback') DEFAULT 'ia',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (tarefa_id) REFERENCES tarefas_estudo(id) ON DELETE SET NULL,
  FOREIGN KEY (plano_id) REFERENCES planos_estudo(id) ON DELETE SET NULL,
  INDEX idx_revisoes_conteudo_usuario (usuario_id),
  INDEX idx_revisoes_conteudo_tarefa (tarefa_id),
  INDEX idx_revisoes_conteudo_materia (materia)
) ENGINE=InnoDB;

UPDATE estudo_perfis
SET onboarding_completo = 1
WHERE onboarding_completo = 0
  AND perfil_json IS NOT NULL
  AND perfil_json <> '';

SET @estudai_0004_tem_escopo := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'planos_estudo'
    AND COLUMN_NAME = 'escopo'
);

SET @estudai_0004_sql := IF(
  @estudai_0004_tem_escopo > 0,
  'UPDATE planos_estudo SET tipo_plano = COALESCE(tipo_plano, IF(COALESCE(escopo, '''') = ''anual'', ''anual'', ''semanal'')) WHERE tipo_plano IS NULL',
  'UPDATE planos_estudo SET tipo_plano = COALESCE(tipo_plano, ''semanal'') WHERE tipo_plano IS NULL'
);

PREPARE estudai_0004_stmt FROM @estudai_0004_sql;
EXECUTE estudai_0004_stmt;
DEALLOCATE PREPARE estudai_0004_stmt;

DROP PROCEDURE IF EXISTS estudai_0004_add_index_if_missing;
DROP PROCEDURE IF EXISTS estudai_0004_add_column_if_missing;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('0004_estudai_weekly_flow_fix', 'Corrige fluxo semanal, onboarding obrigatorio, datas, ENEM e IA contextual');
