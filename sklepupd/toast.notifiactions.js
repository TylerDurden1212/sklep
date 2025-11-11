// toast_notifications.js - Dodaj ten plik i includuj go w każdym pliku PHP

// Style dla toast notifications (dodaj do każdego pliku PHP w <style>)
const toastStyles = `
<style>
.toast-container {
    position: fixed;
    top: 100px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    min-width: 300px;
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideInRight 0.3s ease-out;
    position: relative;
    overflow: hidden;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast.removing {
    animation: slideOutRight 0.3s ease-out forwards;
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.toast-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.toast-message {
    font-size: 14px;
    color: #666;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: 0.3s;
}

.toast-close:hover {
    background: #f0f0f0;
    color: #333;
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 4px;
    background: linear-gradient(90deg, #ff8c42, #ff6b35);
    width: 100%;
    transform-origin: left;
    animation: shrink 5s linear forwards;
}

@keyframes shrink {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}

.toast.success {
    border-left: 5px solid #10b981;
}

.toast.error {
    border-left: 5px solid #ef4444;
}

.toast.warning {
    border-left: 5px solid #f59e0b;
}

.toast.info {
    border-left: 5px solid #3b82f6;
}

@media (max-width: 768px) {
    .toast-container {
        right: 10px;
        left: 10px;
    }
    
    .toast {
        min-width: auto;
    }
}
</style>
`;

// Funkcja wyświetlania toast
function showToast(type, title, message, duration = 5000) {
    // Utwórz container jeśli nie istnieje
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    // Ikony dla różnych typów
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    // Utwórz toast
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${icons[type]}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">×</button>
        <div class="toast-progress"></div>
    `;
    
    container.appendChild(toast);
    
    // Obsługa zamykania
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => removeToast(toast));
    
    // Auto-usuwanie
    if (duration > 0) {
        setTimeout(() => removeToast(toast), duration);
    }
    
    return toast;
}

function removeToast(toast) {
    toast.classList.add('removing');
    setTimeout(() => {
        toast.remove();
        
        // Usuń container jeśli pusty
        const container = document.querySelector('.toast-container');
        if (container && container.children.length === 0) {
            container.remove();
        }
    }, 300);
}

// Przykłady użycia:
// showToast('success', 'Sukces!', 'Produkt został dodany pomyślnie');
// showToast('error', 'Błąd!', 'Nie udało się zapisać zmian');
// showToast('warning', 'Uwaga!', 'Osiągnąłeś limit ogłoszeń');
// showToast('info', 'Info', 'Masz nową wiadomość od użytkownika');

// Eksportuj dla użycia globalnego
window.showToast = showToast;