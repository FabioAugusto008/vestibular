-- EstudAI - Seed inicial de questões autorais estilo ENEM
-- Gerado em 2026-05-24
-- Observação: questões 100% autorais, inspiradas no formato ENEM, sem copiar itens oficiais.
-- Objetivo: base inicial para protótipo funcional com qualidade e sem IA em tempo real.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS questoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area VARCHAR(120) NOT NULL,
  materia VARCHAR(80) NOT NULL,
  conteudo VARCHAR(120) NOT NULL,
  competencia VARCHAR(20) NULL,
  habilidade VARCHAR(20) NULL,
  dificuldade ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
  fonte VARCHAR(120) NOT NULL DEFAULT 'Autoral estilo ENEM',
  ano INT NULL,
  prova VARCHAR(120) NULL,
  enunciado TEXT NOT NULL,
  explicacao TEXT NULL,
  status ENUM('rascunho','revisada','aprovada','arquivada') NOT NULL DEFAULT 'aprovada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_questoes_filtro (area, materia, conteudo, dificuldade, status),
  INDEX idx_questoes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questoes_alternativas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  questao_id INT NOT NULL,
  letra CHAR(1) NOT NULL,
  texto TEXT NOT NULL,
  correta TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_questoes_alternativas_questao
    FOREIGN KEY (questao_id) REFERENCES questoes(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_questao_letra (questao_id, letra),
  INDEX idx_alt_correta (questao_id, correta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questoes_respostas_usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  questao_id INT NOT NULL,
  alternativa_id INT NULL,
  correta TINYINT(1) NOT NULL DEFAULT 0,
  tempo_resposta INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_respostas_usuario (usuario_id, created_at),
  INDEX idx_respostas_questao (questao_id),
  CONSTRAINT fk_respostas_questao
    FOREIGN KEY (questao_id) REFERENCES questoes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_respostas_alternativa
    FOREIGN KEY (alternativa_id) REFERENCES questoes_alternativas(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

START TRANSACTION;

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Português', 'Interpretação de texto', 'C1', 'H1', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em uma campanha de biblioteca escolar, lia-se: “Livro parado não forma leitor. Compartilhe histórias.” O objetivo principal da frase é', 'A frase associa o livro parado à ausência de formação leitora e usa o imperativo “Compartilhe” para incentivar a circulação das histórias.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'informar que livros antigos perdem valor quando não são lidos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'defender que a leitura deve ser uma prática individual e silenciosa.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'estimular a circulação de livros como forma de ampliar o acesso à leitura.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'criticar leitores que preferem textos digitais a livros impressos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'explicar o processo de conservação de obras em bibliotecas.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Português', 'Funções da linguagem', 'C6', 'H18', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em um aplicativo de saúde, aparece a mensagem: “Beba água agora. Seu corpo agradece.” Ao se dirigir diretamente ao usuário para induzir uma ação, predomina a função', 'A função conativa ou apelativa ocorre quando a linguagem procura convencer ou orientar o receptor a agir.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'referencial, pois apresenta dados científicos detalhados.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'metalinguística, pois explica o significado da palavra água.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'emotiva, pois revela sentimentos íntimos do emissor.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'conativa, pois busca influenciar o comportamento do receptor.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'poética, pois privilegia exclusivamente a sonoridade da mensagem.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Português', 'Variação linguística', 'C8', 'H25', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um estudante escreveu em um fórum: “A gente vai entregar o trabalho amanhã, porque tava difícil fechar tudo hoje.” Em uma situação formal, a reescrita mais adequada, sem alterar o sentido, é', 'A alternativa mantém o sentido original e ajusta marcas de informalidade para registro formal.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'Nós entregaremos o trabalho amanhã, pois estava difícil concluir tudo hoje.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'A gente entregava o trabalho amanhã, porque fechou tudo hoje.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'Nós entregaríamos o trabalho ontem, já que estava tudo concluído.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'Eles entregarão o trabalho amanhã, porque estava fácil finalizar hoje.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'A gente vai entregar o trabalho hoje, pois amanhã será difícil.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Artes', 'Leitura de imagem e cultura visual', 'C4', 'H12', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma intervenção urbana cobre temporariamente uma praça de concreto com tapetes verdes feitos de material reciclado. A obra convida os pedestres a sentar, conversar e permanecer no local. Nessa proposta, a arte atua principalmente como', 'A intervenção altera o modo como as pessoas ocupam a praça e propõe reflexão sobre cidade, ambiente e convivência.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'decoração neutra, sem relação com a experiência do espaço.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'produto de luxo, restrito à observação distante do público.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'ação que transforma o uso do espaço e provoca reflexão sobre convivência urbana.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'reprodução fiel da natureza, sem intenção social ou política.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'registro histórico de técnicas tradicionais de pintura.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Inglês', 'Leitura e inferência', 'C2', 'H5', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em um cartaz de aeroporto, lê-se: “Keep your boarding pass ready.” A orientação dada ao passageiro é', 'A expressão “keep ... ready” indica manter algo preparado ou à mão.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'descartar o cartão de embarque após a chegada.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'manter o cartão de embarque pronto para apresentação.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'solicitar um novo cartão antes de sair do aeroporto.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'guardar a bagagem de mão no balcão de atendimento.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'confirmar a reserva em outro terminal.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Literatura', 'Realismo e crítica social', 'C5', 'H16', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em um romance, o narrador descreve um personagem que mantém aparência respeitável em público, mas age de modo interesseiro nas relações privadas. Esse contraste é usado para revelar a hipocrisia de determinado grupo social. Essa estratégia se aproxima de uma característica do', 'O Realismo frequentemente expõe contradições morais, interesses sociais e conflitos psicológicos dos personagens.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'Arcadismo, pela idealização da vida pastoril.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'Realismo, pela crítica aos comportamentos sociais e psicológicos.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'Trovadorismo, pela valorização das cantigas medievais.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'Quinhentismo, pela descrição da terra recém-encontrada.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'Barroco, pela oposição religiosa entre céu e inferno apenas.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Educação Física', 'Corpo, saúde e sociedade', 'C3', 'H10', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma escola substituiu atividades competitivas obrigatórias por circuitos com diferentes níveis de intensidade, permitindo que todos os estudantes participassem conforme suas condições físicas. Essa mudança valoriza', 'A proposta adapta a prática para garantir participação e respeitar diferentes condições corporais.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'a exclusão dos alunos com menor desempenho esportivo.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'a padronização do corpo ideal para todos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'a participação inclusiva e o respeito às diferenças corporais.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'a eliminação de qualquer atividade física da rotina escolar.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'a competição como único objetivo da educação física.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Linguagens, Códigos e suas Tecnologias', 'Português', 'Argumentação', 'C7', 'H23', 'dificil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em um artigo de opinião, o autor afirma: “Não basta instalar lixeiras coloridas; é preciso ensinar o caminho do resíduo depois do descarte.” O argumento central é que políticas ambientais escolares devem', 'O enunciado defende que infraestrutura só é suficiente quando acompanhada de compreensão crítica do processo.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'priorizar a aparência dos espaços em vez da educação ambiental.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'limitar-se à compra de equipamentos de coleta seletiva.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'associar infraestrutura a formação crítica sobre o ciclo dos resíduos.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'substituir aulas de ciências por campanhas publicitárias.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'responsabilizar apenas estudantes pela gestão municipal do lixo.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Porcentagem', 'C1', 'H3', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma escola tinha 800 estudantes. Após uma campanha de matrícula, o número aumentou 12,5%. O total de estudantes passou a ser', '12,5% de 800 é 100. Logo, o novo total é 800 + 100 = 900.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '812', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '850', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '875', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '900', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '925', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Razão e proporção', 'C1', 'H2', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Para preparar 6 litros de suco, uma cantina mistura concentrado e água na razão 1:5. Para preparar 18 litros mantendo a mesma proporção, a quantidade de concentrado necessária é', 'A razão total tem 6 partes, sendo 1 de concentrado. Em 18 litros, 1/6 corresponde a 3 litros.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '1 litro', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '2 litros', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '3 litros', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '5 litros', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '6 litros', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Função afim', 'C5', 'H21', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um aplicativo de transporte cobra taxa fixa de R$ 6,00 mais R$ 2,40 por quilômetro rodado. A função que representa o valor V, em reais, de uma corrida de x quilômetros é', 'Há uma parte fixa de 6 reais e uma parte variável proporcional aos quilômetros: 2,40x.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'V = 6x + 2,40', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'V = 2,40x + 6', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'V = 8,40x', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'V = 6 + x/2,40', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'V = 2,40 + 6x', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Estatística', 'C7', 'H27', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'As notas de cinco estudantes em uma atividade foram 6, 7, 7, 8 e 10. A média dessas notas é', 'A soma é 38. Dividindo por 5, obtém-se 7,6.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '7,0', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '7,2', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '7,4', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '7,6', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '8,0', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Geometria plana', 'C2', 'H8', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um terreno retangular mede 24 m de comprimento e 15 m de largura. Será colocada uma cerca em todo o contorno. O comprimento mínimo de cerca necessário é', 'O contorno é o perímetro: 2 × (24 + 15) = 78 metros.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '39 m', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '78 m', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '180 m', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '360 m', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '720 m', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Escala', 'C3', 'H11', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em um mapa na escala 1:50 000, a distância entre duas cidades é de 7 cm. A distância real entre elas é', '7 cm no mapa representam 7 × 50 000 = 350 000 cm, que correspondem a 3,5 km.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '350 m', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '3,5 km', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '35 km', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '350 km', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '3 500 km', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Probabilidade', 'C7', 'H28', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em uma caixa há 4 bolas azuis, 3 verdes e 5 vermelhas, todas iguais ao toque. Ao retirar uma bola ao acaso, a probabilidade de ela não ser vermelha é', 'Há 12 bolas no total. As não vermelhas são 4 + 3 = 7. A probabilidade é 7/12.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '5/12', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '7/12', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '1/3', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '3/5', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '2/7', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Matemática e suas Tecnologias', 'Matemática', 'Progressão aritmética', 'C5', 'H22', 'dificil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um estudante decidiu resolver 8 questões no primeiro dia e aumentar 3 questões a cada novo dia. No sétimo dia, ele resolverá', 'É uma PA com a1 = 8 e razão 3. No sétimo dia: a7 = 8 + 6×3 = 26.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '18 questões', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '21 questões', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '24 questões', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '26 questões', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '29 questões', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Biologia', 'Ecologia', 'C3', 'H10', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em uma lagoa, o despejo de esgoto aumenta a quantidade de nutrientes disponíveis, favorecendo a proliferação de algas. A decomposição posterior reduz o oxigênio dissolvido e causa morte de peixes. Esse processo é chamado de', 'A eutrofização ocorre pelo excesso de nutrientes em ambientes aquáticos, seguido de proliferação de algas e queda do oxigênio.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'eutrofização', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'biomagnificação', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'sucessão primária', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'isolamento reprodutivo', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'seleção artificial', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Química', 'pH e neutralização', 'C5', 'H17', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um agricultor aplica calcário em um solo muito ácido para melhorar o desenvolvimento das plantas. A ação do calcário está relacionada à', 'O calcário possui caráter básico e é usado para corrigir a acidez do solo, elevando o pH.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'redução do pH por aumento da acidez.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'neutralização parcial da acidez do solo.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'transformação do solo em substância pura.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'eliminação completa da matéria orgânica.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'produção direta de glicose pelas raízes.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Física', 'Energia elétrica', 'C6', 'H21', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma lâmpada de 20 W permanece ligada durante 5 horas por dia. Em 30 dias, o consumo de energia dessa lâmpada será', '20 W = 0,02 kW. O tempo total é 5×30 = 150 h. Energia = 0,02×150 = 3 kWh.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '0,3 kWh', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '3 kWh', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '30 kWh', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '100 kWh', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '300 kWh', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Biologia', 'Genética', 'C4', 'H13', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em uma espécie vegetal, a cor roxa da flor é dominante sobre a cor branca. Dois indivíduos heterozigotos são cruzados. A proporção esperada de plantas com flores brancas é', 'No cruzamento Aa × Aa, a proporção genotípica é 1 AA : 2 Aa : 1 aa. Apenas aa manifesta o fenótipo recessivo, logo 25%.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '0%', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '25%', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '50%', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '75%', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '100%', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Química', 'Separação de misturas', 'C5', 'H18', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em uma estação de tratamento, uma mistura de água e areia passa por um processo em que a fase sólida fica retida em uma barreira porosa. Esse processo é', 'A filtração separa sólido não dissolvido de líquido por meio de uma barreira porosa.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'destilação', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'filtração', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'evaporação', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'fusão', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'sublimação', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Física', 'Cinemática', 'C6', 'H20', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Um ônibus percorre 120 km em 2 horas, mantendo velocidade média constante. Sua velocidade média é', 'Velocidade média é distância dividida pelo tempo: 120/2 = 60 km/h.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '30 km/h', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '45 km/h', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '60 km/h', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '90 km/h', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '240 km/h', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Biologia', 'Fisiologia humana', 'C4', 'H14', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Durante uma corrida, a frequência respiratória aumenta para atender à maior demanda energética dos músculos. Esse aumento contribui principalmente para', 'A respiração mais intensa favorece trocas gasosas, fornecendo oxigênio e removendo CO2 produzido no metabolismo.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'diminuir a chegada de oxigênio ao sangue.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'aumentar a oferta de oxigênio e a eliminação de gás carbônico.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'interromper a circulação sanguínea nos músculos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'impedir a produção de ATP pelas células.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'reduzir a atividade metabólica do organismo.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências da Natureza e suas Tecnologias', 'Química', 'Estequiometria simples', 'C7', 'H24', 'dificil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Na reação 2 H2 + O2 → 2 H2O, a proporção em mol entre gás hidrogênio e água formada é', 'Pela equação balanceada, 2 mol de H2 formam 2 mol de H2O, portanto a proporção H2:H2O é 1:1.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', '1:1', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', '1:2', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', '2:1', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', '2:3', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', '3:2', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'História', 'Era Vargas', 'C3', 'H11', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Durante o Estado Novo, o governo brasileiro fortaleceu a propaganda oficial e restringiu liberdades políticas. Essas práticas indicam uma característica', 'O Estado Novo foi marcado por centralização política, censura e propaganda estatal.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'liberal-democrática, com ampla autonomia partidária.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'autoritária, com centralização do poder e controle da informação.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'anarquista, com ausência de Estado e de instituições públicas.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'federalista radical, com independência total dos estados.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'parlamentarista, com supremacia do Legislativo sobre o Executivo.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Geografia', 'Urbanização', 'C4', 'H16', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em muitas metrópoles, parte da população vive longe dos centros de emprego e gasta horas em deslocamentos diários. Esse fenômeno evidencia', 'A distância entre moradia popular e oportunidades urbanas expressa segregação e desigualdade no espaço urbano.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'distribuição igualitária dos serviços urbanos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'segregação socioespacial e desigualdade de acesso à cidade.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'eliminação da periferia pelo crescimento planejado.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'redução da dependência do transporte coletivo.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'ausência de relação entre moradia e renda.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Filosofia', 'Ética', 'C5', 'H23', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma pessoa devolve uma carteira encontrada na rua porque acredita que essa atitude deve valer como regra para todos, independentemente de recompensa. Essa justificativa se aproxima de uma ética baseada', 'A ideia de agir segundo uma regra que possa valer universalmente aproxima-se da ética do dever.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'no dever e na universalização da ação.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'no prazer imediato como critério único.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'na vantagem pessoal acima da norma.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'na negação de qualquer princípio moral.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'na obediência apenas ao medo da punição.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Sociologia', 'Cidadania e participação', 'C5', 'H22', 'facil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Moradores de um bairro organizam uma reunião para cobrar iluminação pública, transporte e segurança. Essa ação exemplifica', 'A mobilização coletiva por serviços públicos é uma forma de participação cidadã.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'isolamento político da comunidade.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'participação social na reivindicação de direitos.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'substituição completa do Estado por indivíduos.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'negação da cidadania coletiva.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'desinteresse pela vida pública.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Geografia', 'Globalização', 'C4', 'H18', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Uma empresa projeta um produto em um país, fabrica componentes em outro e vende o item em diversos mercados. Essa dinâmica revela', 'A produção distribuída por diferentes países é característica das cadeias globais e da fragmentação produtiva.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'isolamento econômico entre territórios.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'fragmentação internacional da produção.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'fim das trocas comerciais globais.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'redução completa da tecnologia no trabalho.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'ausência de redes de transporte e comunicação.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'História', 'Brasil República', 'C2', 'H8', 'medio', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'A Constituição de 1988 ampliou direitos sociais e mecanismos de participação após um período de regime autoritário. Por isso, é frequentemente associada à', 'A Constituição de 1988 marcou a ampliação de direitos e a institucionalização do processo democrático após a ditadura.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'restrição do voto direto.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'consolidação da redemocratização brasileira.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'extinção dos direitos trabalhistas.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'centralização monárquica do poder.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'suspensão permanente das liberdades civis.', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Geografia', 'Climatologia', 'C6', 'H26', 'dificil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Em áreas urbanas densamente ocupadas, a substituição de vegetação por asfalto e concreto contribui para temperaturas mais elevadas que nas áreas rurais próximas. Esse fenômeno é conhecido como', 'A ilha de calor urbana ocorre pela concentração de materiais que absorvem calor, pouca vegetação e emissão de calor por atividades humanas.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'ilha de calor', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'chuva orográfica', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'inversão marítima', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'erosão laminar', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'corrente de convecção oceânica', 0);

INSERT INTO questoes
(area, materia, conteudo, competencia, habilidade, dificuldade, fonte, ano, prova, enunciado, explicacao, status)
VALUES
('Ciências Humanas e suas Tecnologias', 'Sociologia', 'Trabalho e tecnologia', 'C4', 'H20', 'dificil', 'Autoral estilo ENEM', 2026, 'EstudAI ENEM Base 01', 'Aplicativos de entrega permitem flexibilidade de horário, mas transferem ao trabalhador custos como manutenção do veículo, internet e riscos do deslocamento. Essa relação expressa uma transformação do trabalho marcada por', 'A plataformização pode combinar autonomia aparente com transferência de custos e riscos ao trabalhador, caracterizando precarização.', 'aprovada');

SET @questao_id = LAST_INSERT_ID();

INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'A', 'ampliação plena de direitos trabalhistas tradicionais.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'B', 'desaparecimento da mediação tecnológica.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'C', 'precarização associada à plataformização do trabalho.', 1);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'D', 'fim da necessidade de renda para os trabalhadores.', 0);
INSERT INTO questoes_alternativas (questao_id, letra, texto, correta) VALUES (@questao_id, 'E', 'controle coletivo dos meios de produção pelos entregadores.', 0);

COMMIT;
