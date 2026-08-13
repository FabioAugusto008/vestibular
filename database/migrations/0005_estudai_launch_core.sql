-- EstudAI 0.1.0-alpha - Launch Core
-- Migration incremental, segura e nao destrutiva.

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(80) PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP PROCEDURE IF EXISTS estudai_0005_add_column_if_missing;

DELIMITER $$

CREATE PROCEDURE estudai_0005_add_column_if_missing(
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

DELIMITER ;

CALL estudai_0005_add_column_if_missing(
  'tarefas_estudo',
  'tempo_real_min',
  'tempo_real_min INT NULL DEFAULT 0'
);

CALL estudai_0005_add_column_if_missing(
  'respostas_exercicios_planejados',
  'motivo_erro',
  'motivo_erro VARCHAR(40) NULL'
);

CREATE TABLE IF NOT EXISTS redacoes_enem (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tema VARCHAR(180) NOT NULL,
  texto MEDIUMTEXT NOT NULL,
  status ENUM('rascunho','analisada','arquivada') NOT NULL DEFAULT 'rascunho',
  analise_json LONGTEXT NULL,
  criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizada_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_redacoes_usuario (usuario_id, atualizada_em),
  CONSTRAINT fk_redacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO conquistas (codigo, nome, descricao, icone, categoria, requisito) VALUES
('questoes_100', '100 questoes respondidas', 'Respondeu 100 questoes da base aprovada.', 'target', 'questoes', 100),
('primeira_semana_completa', 'Primeira semana completa', 'Concluiu todas as tarefas validas da semana.', 'calendar', 'especial', 1),
('semana_sem_atrasos', 'Semana sem atrasos', 'Manteve a semana atual sem tarefas atrasadas.', 'check', 'especial', 1),
('revisou_10_erros', 'Revisou 10 erros', 'Revisitou pelo menos 10 erros reais registrados.', 'refresh', 'especial', 10),
('plano_semanal_concluido', 'Plano semanal concluido', 'Concluiu um plano semanal salvo no EstudAI.', 'clipboard', 'especial', 1),
('primeira_redacao', 'Primeira redacao', 'Salvou ou analisou a primeira redacao ENEM.', 'pencil', 'especial', 1);

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('0005_estudai_launch_core', 'Launch Core: rotina, replanejamento, redacao, PWA, seguranca e conquistas');

DROP PROCEDURE IF EXISTS estudai_0005_add_column_if_missing;
