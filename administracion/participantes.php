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

// Fetch participantes with expositor name
$sql = "SELECT p.*, CONCAT(e.nombre, ' ', e.apellido) as nombre_expositor 
        FROM participantes p 
        LEFT JOIN expositores e ON p.expositor_id = e.id 
        ORDER BY p.nombre_completo ASC";
$stmt = $db->query($sql);
$participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expositores for dropdown
$stmt = $db->query("SELECT id, nombre, apellido FROM expositores ORDER BY nombre ASC");
$expositores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Participantes - Administración</title>
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
                        <a class="nav-link" href="empresas.php">
                            <i class="fas fa-building me-2"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="expositores.php">
                            <i class="fas fa-id-badge me-2"></i> Expositores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="participantes.php">
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
                    <h1 class="h2">Gestión de Participantes</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i> Nuevo Participante
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
                            <table id="participantesTable" class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">ID</th>
                                        <th>Nombre Completo</th>
                                        <th>Cargo/Puesto</th>
                                        <th>Expositor Asociado</th>
                                        <th>Empresa (Texto)</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participantes as $participante): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo htmlspecialchars($participante['id']); ?></td>
                                        <td><?php echo htmlspecialchars($participante['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($participante['cargo_puesto']); ?></td>
                                        <td><?php echo htmlspecialchars($participante['nombre_expositor'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($participante['empresa']); ?></td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal" 
                                                data-id="<?php echo $participante['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($participante['nombre_completo']); ?>"
                                                data-cargo="<?php echo htmlspecialchars($participante['cargo_puesto']); ?>"
                                                data-expositor="<?php echo htmlspecialchars($participante['expositor_id']); ?>"
                                                data-empresa="<?php echo htmlspecialchars($participante['empresa']); ?>"
                                                data-correo="<?php echo htmlspecialchars($participante['correo']); ?>"
                                                data-telefono="<?php echo htmlspecialchars($participante['telefono']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal" 
                                                data-id="<?php echo $participante['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($participante['nombre_completo']); ?>">
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Participante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/participantes_action.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" name="nombre_completo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cargo_puesto" class="form-label">Cargo/Puesto</label>
                                <input type="text" class="form-control" name="cargo_puesto" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expositor_id" class="form-label">Expositor Asociado</label>
                                <select class="form-select" name="expositor_id" required>
                                    <option value="">Seleccione un expositor</option>
                                    <?php foreach ($expositores as $exp): ?>
                                        <option value="<?php echo $exp['id']; ?>"><?php echo htmlspecialchars($exp['nombre'] . ' ' . $exp['apellido']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="empresa" class="form-label">Empresa (Texto)</label>
                                <input type="text" class="form-control" name="empresa">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="correo" class="form-label">Correo</label>
                                <input type="email" class="form-control" name="correo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono">
                            </div>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Participante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/participantes_action.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nombre_completo" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="edit_nombre_completo" name="nombre_completo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_cargo_puesto" class="form-label">Cargo/Puesto</label>
                                <input type="text" class="form-control" id="edit_cargo_puesto" name="cargo_puesto" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_expositor_id" class="form-label">Expositor Asociado</label>
                                <select class="form-select" id="edit_expositor_id" name="expositor_id" required>
                                    <option value="">Seleccione un expositor</option>
                                    <?php foreach ($expositores as $exp): ?>
                                        <option value="<?php echo $exp['id']; ?>"><?php echo htmlspecialchars($exp['nombre'] . ' ' . $exp['apellido']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_empresa" class="form-label">Empresa (Texto)</label>
                                <input type="text" class="form-control" id="edit_empresa" name="empresa">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_correo" class="form-label">Correo</label>
                                <input type="email" class="form-control" id="edit_correo" name="correo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="edit_telefono" name="telefono">
                            </div>
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
                    <h5 class="modal-title">Eliminar Participante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/participantes_action.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar al participante <strong id="delete_nombre"></strong>?</p>
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
            $('#participantesTable').DataTable({
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
            var cargo = button.getAttribute('data-cargo');
            var expositor = button.getAttribute('data-expositor');
            var empresa = button.getAttribute('data-empresa');
            var correo = button.getAttribute('data-correo');
            var telefono = button.getAttribute('data-telefono');
            
            editModal.querySelector('#edit_id').value = id;
            editModal.querySelector('#edit_nombre_completo').value = nombre;
            editModal.querySelector('#edit_cargo_puesto').value = cargo;
            editModal.querySelector('#edit_expositor_id').value = expositor;
            editModal.querySelector('#edit_empresa').value = empresa;
            editModal.querySelector('#edit_correo').value = correo;
            editModal.querySelector('#edit_telefono').value = telefono;
        });

        // Delete Modal Script
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nombre = button.getAttribute('data-nombre');
            
            deleteModal.querySelector('#delete_id').value = id;
            deleteModal.querySelector('#delete_nombre').textContent = nombre;
        });
    </script>
</body>
</html>
