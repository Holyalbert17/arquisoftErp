<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= csrf_meta() ?>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="base-url" content="<?= base_url() ?>">
    <title><?= isset($title) ? $title . ' - ' : '' ?>Sistema de Gestión Multiempresa</title>

    <!-- Google Fonts: Inter & Source Sans Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Source+Sans+Pro:300,400,400i,700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AdminLTE v3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">

    <?= $this->renderSection('styles') ?>
    <?php
    // Determinar modo oscuro/claro con prioridad: Usuario > Sucursal > Empresa
    $modoOscuro = false;

    // Prioridad 1: Campo tema_oscuro directo del usuario
    if (isset($user) && $user) {
        if (isset($user['tema_oscuro']) && $user['tema_oscuro'] !== null) {
            $modoOscuro = ($user['tema_oscuro'] === true || $user['tema_oscuro'] === 't' || $user['tema_oscuro'] === 1 || $user['tema_oscuro'] === '1');
        }
        // Si no hay campo directo, verificar preferencias JSON
        elseif (!empty($user['preferencias'])) {
            try {
                $preferencias = is_string($user['preferencias']) ? json_decode($user['preferencias'], true) : $user['preferencias'];
                if (isset($preferencias['tema_oscuro']) && $preferencias['tema_oscuro']) {
                    $modoOscuro = true;
                }
            } catch (\Exception $e) {
                // Si hay error al parsear, continuar con siguiente nivel
            }
        }
    }

    // Prioridad 2: Campo tema_oscuro de la sucursal
    if (!$modoOscuro && isset($sucursal) && $sucursal && isset($sucursal['tema_oscuro']) && $sucursal['tema_oscuro'] !== null) {
        $modoOscuro = ($sucursal['tema_oscuro'] === true || $sucursal['tema_oscuro'] === 't' || $sucursal['tema_oscuro'] === 1 || $sucursal['tema_oscuro'] === '1');
    }

    // Prioridad 3: Campo tema_oscuro de la empresa
    if (!$modoOscuro && isset($empresa) && $empresa) {
        if (isset($empresa['tema_oscuro']) && $empresa['tema_oscuro'] !== null) {
            $modoOscuro = ($empresa['tema_oscuro'] === true || $empresa['tema_oscuro'] === 't' || $empresa['tema_oscuro'] === 1 || $empresa['tema_oscuro'] === '1');
        }
        // Fallback: Verificar configuración antigua (ConfiguracionModel)
        elseif (isset($empresa['id'])) {
            try {
                $configModel = new \App\Models\ConfiguracionModel();
                $temaEmpresa = $configModel->getConfiguracion($empresa['id'], 'SISTEMA', 'tema_oscuro', 'false');
                $modoOscuro = ($temaEmpresa === 'true' || $temaEmpresa === true || $temaEmpresa === 1);
            } catch (\Exception $e) {
                // Si hay error, usar modo claro por defecto
            }
        }
    }
    ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed <?= $modoOscuro ? 'dark-mode' : '' ?>"
    data-bs-theme="<?= $modoOscuro ? 'dark' : 'light' ?>" data-layout="fixed">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand <?= $modoOscuro ? 'navbar-dark' : 'navbar-white navbar-light' ?>">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button" data-bs-toggle="tooltip"
                        data-bs-placement="right" title="Toggle sidebar"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?= base_url('dashboard') ?>" class="nav-link">Inicio</a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ms-auto">
                <!-- Dark Mode Toggle -->
                <li class="nav-item">
                    <a class="nav-link" href="#" id="toggleDarkMode" title="Cambiar modo oscuro/claro">
                        <i class="fas <?= $modoOscuro ? 'fa-sun' : 'fa-moon' ?>" id="darkModeIcon"></i>
                    </a>
                </li>

                <!-- Notifications Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-bs-toggle="dropdown" href="#">
                        <i class="far fa-bell"></i>
                        <span class="badge bg-warning navbar-badge" id="notifications-count">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" id="notifications-menu">
                        <span class="dropdown-item dropdown-header">0 Notificaciones</span>
                        <div class="dropdown-divider"></div>
                        <div id="notifications-list">
                            <a href="#" class="dropdown-item">
                                <i class="fas fa-envelope me-2"></i> No hay notificaciones
                            </a>
                        </div>
                    </div>
                </li>

                <!-- User Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-bs-toggle="dropdown" href="#">
                        <i class="far fa-user"></i>
                        <?= isset($user) && $user ? esc($user['nombre'] . ' ' . $user['apellido']) : 'Usuario' ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <a href="<?= base_url('perfil') ?>" class="dropdown-item">
                            <i class="fas fa-user me-2"></i> Mi Perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= base_url('auth/logout') ?>" class="dropdown-item">
                            <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar <?= $modoOscuro ? 'sidebar-dark-primary' : 'sidebar-light-primary' ?> elevation-4">
            <!-- Brand Logo -->
            <div class="brand-wrapper">
                <a href="<?= base_url('dashboard') ?>" class="brand-link">
                    <div class="brand-logo-container">
                        <?php if (isset($empresa) && $empresa && $empresa['logo']): ?>
                            <img src="<?= base_url('uploads/empresas/' . $empresa['logo']) ?>" alt="Logo"
                                class="brand-image elevation-3" style="opacity: .8">
                        <?php else: ?>
                            <div class="brand-image img-circle elevation-3 brand-icon-placeholder">
                                <i class="fas fa-building"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="brand-text-container">
                        <span class="brand-text font-weight-bold">
                            <?= isset($empresa) && $empresa ? esc($empresa['nombre']) : 'Sistema' ?>
                        </span>
                    </div>
                </a>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <?php if (isset($user) && $user && $user['foto_perfil']): ?>
                            <img src="<?= base_url('uploads/usuarios/' . $user['foto_perfil']) ?>"
                                class="elevation-2 user-image-custom" alt="User Image">
                        <?php else: ?>
                            <div class="user-icon-placeholder img-circle elevation-2">
                                <i class="fas fa-user-circle"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <a href="<?= base_url('perfil') ?>" class="d-block user-name">
                            <?= isset($user) && $user ? esc($user['nombre'] . ' ' . $user['apellido']) : 'Usuario' ?>
                        </a>
                        <?php if (isset($sucursal) && $sucursal): ?>
                            <small class="text-muted user-location"><?= esc($sucursal['nombre']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">
                        <?= $this->include('layouts/menu') ?>
                    </ul>
                </nav>
                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?= isset($title) ? $title : 'Dashboard' ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Inicio</a></li>
                                <?= $this->renderSection('breadcrumb') ?>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.content-header -->

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <?= $this->renderSection('content') ?>
                </div>
            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

        <footer class="main-footer">
            <strong>Copyright &copy; <?= date('Y') ?> Sistema de Gestión Multiempresa.</strong>
            Todos los derechos reservados.
        </footer>

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- Toastr -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables Español (local) -->
    <script src="<?= base_url('assets/js/datatables-es.js') ?>"></script>
    <!-- Custom JS -->
    <script src="<?= base_url('assets/js/custom.js') ?>"></script>
    <script src="<?= base_url('assets/js/app-modal.js') ?>"></script>

    <?= $this->renderSection('scripts') ?>

    <!-- Modal dinámico global (fuera del wrapper, evita problemas de z-index) -->
    <div id="app-modal-overlay" class="app-modal-overlay" style="display: none;" aria-hidden="true">
        <div class="app-modal-backdrop"></div>
        <div class="app-modal-container" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
            <div class="app-modal-box">
                <div class="app-modal-header">
                    <h5 id="app-modal-title" class="app-modal-title"></h5>
                    <button type="button" class="app-modal-close" aria-label="Cerrar">&times;</button>
                </div>
                <div class="app-modal-body"></div>
            </div>
        </div>
    </div>
</body>

</html>