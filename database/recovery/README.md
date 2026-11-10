# Banco limpo de testes — Etapa 06

O instalador aceita exclusivamente `estudai_teste_2026`, no MySQL local cuja
identidade consta no manifesto. Recusa qualquer banco já existente, incluindo
uma instalação parcial. Não lê `.env`, não executa migrations, não limpa tabelas
e não reutiliza dados pessoais. Sua execução administrativa não usa a conta da
aplicação.

## Arquivos revisáveis

- `structure_stage06.json`: 42 `SHOW CREATE TABLE` validados, com 65 FKs,
  removendo somente o contador histórico da opção de tabela `AUTO_INCREMENT`.
  As colunas auto-incrementais, índices, tipos, defaults e ENUMs são preservados.
- `catalog_stage06.json`: cinco catálogos estáticos. Nenhuma outra tabela recebe
  dados durante a instalação. Datas históricas dos catálogos foram omitidas.
- `manifest_stage06.json`: origem, hashes, deduplicação e rótulos derivados.
- `install_clean_test_database.py`: valida as fixtures por padrão, sem banco.

Os 64 registros antigos formavam 32 pares equivalentes, inclusive alternativas
e gabarito. São importadas 32 questões com cinco alternativas A–E e exatamente
uma correta por questão: 160 alternativas.

As 7 matérias e 20 conteúdos não abrangiam todos os rótulos das questões.
Foram acrescentadas 6 matérias e 22 conteúdos, exclusivamente a partir dos
rótulos já existentes, totalizando 13 matérias e 42 conteúdos. Nenhuma questão
ou alternativa foi inventada. Os 22 registros estáticos de conquistas foram
mantidos. Questões usam matéria/conteúdo em texto, sem FKs para esses catálogos;
o vínculo por rótulo foi conferido com normalização de acentos e espaços.

A procedure histórica de migração com DEFINER administrativo não é necessária
a cadastro, login ou onboarding e não é reproduzida. `schema_migrations` começa
vazia: as tabelas foram reproduzidas por metadados, sem aplicar migrations.

## Validação e instalação

`python database/recovery/install_clean_test_database.py` somente valida.

A opção `--create` exige `ESTUDAI_BOOTSTRAP_PASSWORD` fornecida em memória por
um prompt protegido; nunca colocar a senha literalmente na linha de comando,
em scripts ou logs. O cliente oficial MySQL recebe a credencial apenas no
ambiente do processo filho, sem arquivo de credenciais.

O instalador revalida servidor e ausência do destino imediatamente antes de
criar o banco. Cria primeiro as tabelas, depois as FKs; não desativa a checagem
referencial. Insere apenas os catálogos em transação e verifica contagens e
métricas estruturais. Nunca reaplicar sobre banco existente. Em caso de falha,
preservar o estado parcial e investigar, sem excluir ou substituir objetos.
