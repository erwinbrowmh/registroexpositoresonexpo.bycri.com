<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

// Validate Session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Fetch companies
$stmt = $db->query("SELECT * FROM empresas ORDER BY nombre_empresa ASC");
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empresas - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #343a40; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding: 0.8rem 1rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background: rgba(255,255,255,.1); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block">
                <div class="p-3 text-center border-bottom border-secondary">
                    <h5 class="m-0 fw-bold"><i class="fas fa-shield-alt me-2"></i>Admin Panel</h5>
                    <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></small>
                </div>
                <ul class="nav flex-column py-3">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-2"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="empresas.php">
                            <i class="fas fa-building me-2"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="expositores.php">
                            <i class="fas fa-id-badge me-2"></i> Expositores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="participantes.php">
                            <i class="fas fa-users me-2"></i> Participantes
                        </a>
                    </li>
                    <?php if (!empty($_SESSION['can_manage_admins'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="administradores.php">
                            <i class="fas fa-user-shield me-2"></i> Administradores
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Empresas</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i> Nueva Empresa
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="empresasTable" class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">ID</th>
                                        <th>Nombre de Empresa</th>
                                        <th>Límite Participantes</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empresas as $empresa): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo htmlspecialchars($empresa['id']); ?></td>
                                        <td><?php echo htmlspecialchars($empresa['nombre_empresa']); ?></td>
                                        <td><?php echo htmlspecialchars($empresa['limite_participantes']); ?></td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal" 
                                                data-id="<?php echo $empresa['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>"
                                                data-limite="<?php echo htmlspecialchars($empresa['limite_participantes']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal" 
                                                data-id="<?php echo $empresa['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/empresas_action.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_empresa" class="form-label">Nombre Empresa</label>
                            <input type="text" class="form-control" name="nombre_empresa" required>
                        </div>
                        <div class="mb-3">
                            <label for="limite_participantes" class="form-label">Límite Participantes</label>
                            <input type="number" class="form-control" name="limite_participantes" value="5" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/empresas_action.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_nombre_empresa" class="form-label">Nombre Empresa</label>
                            <input type="text" class="form-control" id="edit_nombre_empresa" name="nombre_empresa" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_limite_participantes" class="form-label">Límite Participantes</label>
                            <input type="number" class="form-control" id="edit_limite_participantes" name="limite_participantes" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/empresas_action.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar la empresa <strong id="delete_nombre"></strong>?</p>
                        <p class="text-danger small">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#empresasTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                columnDefs: [
                    { orderable: false, targets: -1 } // Disable sorting on last column (actions)
                ]
            });
        });

        // Edit Modal Script
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nombre = button.getAttribute('data-nombre');
            var limite = button.getAttribute('data-limite');
            
            var modalId = editModal.querySelector('#edit_id');
            var modalNombre = editModal.querySelector('#edit_nombre_empresa');
            var modalLimite = editModal.querySelector('#edit_limite_participantes');
            
            modalId.value = id;
            modalNombre.value = nombre;
            modalLimite.value = limite;
        });

        // Delete Modal Script
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nombre = button.getAttribute('data-nombre');
            
            var modalId = deleteModal.querySelector('#delete_id');
            var modalNombre = deleteModal.querySelector('#delete_nombre');
            
            modalId.value = id;
            modalNombre.textContent = nombre;
        });
    </script>
</body>
</html>
