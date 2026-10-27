// Toastr Configuration
if (typeof toastr !== 'undefined') {
    toastr.options = {
    "closeButton": true,
    "debug": false,
    "newestOnTop": true,
    "progressBar": true,
    "positionClass": "toast-top-right",
    "preventDuplicates": false,
    "onclick": null,
    "showDuration": "300",
    "hideDuration": "1000",
    "timeOut": "5000",
    "extendedTimeOut": "1000",
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
    "hideMethod": "fadeOut"
    };
}

// Custom Toastr Functions
window.showSuccess = function(message, title = 'Success') {
    toastr.success(message, title);
};

window.showError = function(message, title = 'Error') {
    toastr.error(message, title);
};

window.showWarning = function(message, title = 'Warning') {
    toastr.warning(message, title);
};

window.showInfo = function(message, title = 'Info') {
    toastr.info(message, title);
};