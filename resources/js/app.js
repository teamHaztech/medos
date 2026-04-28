import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Global Alpine stores
Alpine.store('flash', {
    show: false,
    type: 'success',
    message: '',
    display(type, message) {
        this.type = type;
        this.message = message;
        this.show = true;
        setTimeout(() => { this.show = false; }, 5000);
    }
});

Alpine.start();
