import '../css/frontend.css';
import AURRegistrationForm from './components/RegistrationForm';

document.addEventListener('DOMContentLoaded', () => {
    const formContainers = document.querySelectorAll('#aur-registration-form');

    formContainers.forEach(container => {
        new AURRegistrationForm(container);
    });
});