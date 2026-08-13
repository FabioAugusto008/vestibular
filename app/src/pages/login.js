// Verifica se ja esta logado
apiFetch(apiEndpoint('auth', 'action=status'))
  .then(r => r.json())
  .then(d => { if (d.logado) window.location.href = 'app.html'; })
  .catch(() => {});

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[char]);
}

function switchTab(tab) {
  document.querySelectorAll('.tab').forEach((t, i) =>
    t.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'cadastro'))
  );
  document.getElementById('form-login').classList.toggle('hidden', tab !== 'login');
  document.getElementById('form-cadastro').classList.toggle('hidden', tab !== 'cadastro');
  // Clear alerts
  document.getElementById('alert-login').className = 'alert';
  document.getElementById('alert-cadastro').className = 'alert';
}

function showAlert(id, msg, tipo) {
  const el = document.getElementById(id);
  el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    ${tipo === 'error' 
      ? '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
      : '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />'}
  </svg>${escapeHtml(msg)}`;
  el.className = 'alert ' + tipo;
}

async function fazerLogin() {
  const email = document.getElementById('login-email').value.trim();
  const senha = document.getElementById('login-senha').value;
  if (!email || !senha) return showAlert('alert-login', 'Preencha todos os campos.', 'error');

  const btn = document.getElementById('btn-login');
  btn.disabled = true;
  btn.classList.add('loading');

  const fd = new FormData();
  fd.append('action', 'login');
  fd.append('email', email);
  fd.append('senha', senha);

  try {
    const r = await apiFetch(apiEndpoint('auth'), { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) { window.location.href = 'app.html'; }
    else showAlert('alert-login', d.erro, 'error');
  } catch (e) { showAlert('alert-login', e.message || 'Erro de conexao. Tente novamente.', 'error'); }
  finally { 
    btn.disabled = false; 
    btn.classList.remove('loading');
  }
}

async function fazerCadastro() {
  const nome  = document.getElementById('cad-nome').value.trim();
  const email = document.getElementById('cad-email').value.trim();
  const senha = document.getElementById('cad-senha').value;

  if (!nome || !email || !senha) return showAlert('alert-cadastro', 'Preencha todos os campos.', 'error');
  if (senha.length < 6) return showAlert('alert-cadastro', 'A senha deve ter no minimo 6 caracteres.', 'error');

  const fd = new FormData();
  fd.append('action', 'cadastrar');
  fd.append('nome', nome);
  fd.append('email', email);
  fd.append('senha', senha);

  const btn = document.getElementById('btn-cadastro');
  btn.disabled = true;
  btn.classList.add('loading');

  try {
    const r = await apiFetch(apiEndpoint('auth'), { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      showAlert('alert-cadastro', 'Conta criada com sucesso! Redirecionando...', 'success');
      setTimeout(() => switchTab('login'), 1500);
    } else showAlert('alert-cadastro', d.erro, 'error');
  } catch (e) { showAlert('alert-cadastro', e.message || 'Erro de conexao. Tente novamente.', 'error'); }
  finally { 
    btn.disabled = false; 
    btn.classList.remove('loading');
  }
}

// Enter to submit
document.getElementById('login-senha').addEventListener('keydown', e => { if (e.key === 'Enter') fazerLogin(); });
document.getElementById('cad-senha').addEventListener('keydown', e => { if (e.key === 'Enter') fazerCadastro(); });
