/**
 * Custom JavaScript for AdminLTE
 */

// Aplicar modo oscuro desde localStorage inmediatamente (antes de que se cargue jQuery)
// AdminLTE v3 usa data-bs-theme para el modo oscuro
(function () {
    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        document.body.classList.add('dark-mode');
        document.body.setAttribute('data-bs-theme', 'dark');
    } else if (savedDarkMode === 'false') {
        document.body.classList.remove('dark-mode');
        document.body.setAttribute('data-bs-theme', 'light');
    }
})();

$(document).ready(function () {
    // Dark Mode Toggle - AdminLTE v3
    function initDarkMode() {
        // Verificar localStorage primero
        const savedDarkMode = localStorage.getItem('darkMode');
        const $body = $('body');
        const $icon = $('#darkModeIcon');

        if (savedDarkMode === 'true') {
            if (!$body.hasClass('dark-mode')) {
                $body.addClass('dark-mode');
                $body.attr('data-bs-theme', 'dark');
            }
            $icon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            if ($body.hasClass('dark-mode')) {
                $body.removeClass('dark-mode');
                $body.attr('data-bs-theme', 'light');
            }
            $icon.removeClass('fa-sun').addClass('fa-moon');
        }
    }

    // Inicializar icono según el modo actual
    initDarkMode();

    // Toggle Dark Mode - AdminLTE v3
    $('#toggleDarkMode').on('click', function (e) {
        e.preventDefault();
        const $body = $('body');
        const $icon = $('#darkModeIcon');
        const isDarkMode = $body.hasClass('dark-mode');

        if (isDarkMode) {
            // Cambiar a modo claro
            $body.removeClass('dark-mode');
            $body.attr('data-bs-theme', 'light');
            $icon.removeClass('fa-sun').addClass('fa-moon');
        } else {
            // Cambiar a modo oscuro
            $body.addClass('dark-mode');
            $body.attr('data-bs-theme', 'dark');
            $icon.removeClass('fa-moon').addClass('fa-sun');
        }

        // Guardar preferencia
        saveDarkModePreference(!isDarkMode);
    });

    // Guardar preferencia de modo oscuro
    function saveDarkModePreference(isDark) {
        // Guardar en localStorage
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');

        // Guardar en servidor si hay token CSRF
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const baseUrl = $('meta[name="base-url"]').attr('content') || '';
        if (csrfToken && baseUrl) {
            $.ajax({
                url: baseUrl + '/perfil/guardar-tema',
                method: 'POST',
                data: {
                    tema_oscuro: isDark ? 1 : 0
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function (response) {
                    // Preferencia guardada
                },
                error: function () {
                    // Error al guardar, pero la preferencia local está guardada
                }
            });
        }
    }

    // Initialize DataTables - REMOVED to prevent "Cannot reinitialise DataTable" error
    // Individual views should initialize their own DataTables with specific configs
    /*
    if ($.fn.DataTable && typeof datatablesEsConfig !== 'undefined') {
        $('.data-table').DataTable({
            'language': datatablesEsConfig.language,
            'responsive': true,
            'pageLength': 25,
            'order': [[0, 'desc']]
        });
    }
    */

    // Initialize Select2
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            language: 'es'
        });
    }

    // Confirm delete
    $('.btn-delete').on('click', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');

        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });

    // Auto-hide alerts
    setTimeout(function () {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Format currency - FIXED for Guaraníes format
    $('.currency').each(function () {
        let text = $(this).text().trim();

        // Si ya tiene "Gs." al final, no reformatear (ya está formateado por PHP)
        if (text.includes('Gs.')) {
            return; // Skip - already formatted correctly by formatGs()
        }

        // Solo formatear si es un número sin formato
        const value = parseFloat(text.replace(/[^\d.-]/g, ''));
        if (!isNaN(value)) {
            $(this).text(new Intl.NumberFormat('es-PY', {
                style: 'currency',
                currency: 'PYG',
                minimumFractionDigits: 0
            }).format(value));
        }
    });

    // Format numbers
    $('.number').each(function () {
        const value = parseFloat($(this).text().replace(/[^\d.-]/g, ''));
        if (!isNaN(value)) {
            $(this).text(new Intl.NumberFormat('es-PY').format(value));
        }
    });

    // Format dates
    $('.date').each(function () {
        const date = new Date($(this).text());
        if (!isNaN(date.getTime())) {
            $(this).text(date.toLocaleDateString('es-PY', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }));
        }
    });

    // Format datetime
    $('.datetime').each(function () {
        const date = new Date($(this).text());
        if (!isNaN(date.getTime())) {
            $(this).text(date.toLocaleString('es-PY', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }));
        }
    });

    // Loading overlay
    window.showLoading = function () {
        $('body').append('<div class="spinner-overlay"><div class="spinner"></div></div>');
    };

    window.hideLoading = function () {
        $('.spinner-overlay').remove();
    };

    // AJAX setup
    $.ajaxSetup({
        beforeSend: function () {
            showLoading();
        },
        complete: function () {
            hideLoading();
        },
        error: function (xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al procesar la solicitud'
            });
        }
    });

    // Asegurar que todos los modales funcionen correctamente (Bootstrap 5)
    // Eliminamos parches de z-index conflictivos. Bootstrap maneja esto bien si no se interfiere.

    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Helper global para DataTables
    window.initDataTable = function (selector = '.data-table', options = {}) {
        if (!$.fn.DataTable) return;

        const defaults = {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            responsive: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            pageLength: 25,
            order: [[0, 'desc']],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
        };

        const settings = $.extend(true, {}, defaults, options);

        // Destruir si existe para reinicializar limpio
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }

        return $(selector).DataTable(settings);
    };

    // Inicializar tablas automáticas (Solo si tienen la clase .auto-datatable para evitar conflictos)
    if ($('.auto-datatable').length > 0) {
        initDataTable('.auto-datatable');
    }
});

// Utility functions
const Utils = {
    formatCurrency: function (value, currency = 'PYG') {
        return new Intl.NumberFormat('es-PY', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0
        }).format(value);
    },

    formatNumber: function (value) {
        return new Intl.NumberFormat('es-PY').format(value);
    },

    formatDate: function (date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('es-PY', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    },

    formatDateTime: function (date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleString('es-PY', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    showSuccess: function (message) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: message,
            timer: 2000,
            showConfirmButton: false
        });
    },

    showError: function (message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    },

    confirm: function (message, callback) {
        Swal.fire({
            title: '¿Está seguro?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    }
};
