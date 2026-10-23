import '../styles/login.scss';

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('togglePassword');
    const input = document.getElementById('inputPassword');
    toggle?.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.querySelector('i').className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
    });

    document.querySelector('.login-form')?.addEventListener('submit', (e) => {
        const btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Accesso...';
    });
});
