# Onboarding persistido - 0.0.2-alpha

## Fonte principal

O onboarding agora usa o backend como fonte principal por `api/onboarding.php`.

- `GET ?action=status`: informa se o perfil foi concluido.
- `GET ?action=carregar`: retorna perfil e ultimo snapshot.
- `POST ?action=salvar`: valida e salva o perfil.

O `localStorage` continua apenas como fallback de leitura para nao quebrar usuarios da `0.0.1-alpha`.

## Campos

- `objetivo`
- `data_prova`
- `horas_dia`
- `dias_semana`
- `dificuldades`
- `prioridades`
- `preferencia_estudo`
- `meta_semanal`
- `notificacoes`
- `respostas`

## Seguranca

- Exige login.
- Filtra por `usuario_id`.
- Valida CSRF no POST.
- Limita tamanho de payload e textos.
- Salva snapshot em `onboarding_respostas` com versao `0.0.2-alpha`.
