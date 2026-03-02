document.addEventListener('DOMContentLoaded', () => {
    const expositorForm = document.getElementById('expositorForm');
    const participanteForm = document.getElementById('participanteForm');
    const participantsTableBody = document.querySelector('#participantsTable tbody');
    const expositoresTableBody = document.querySelector('#expositoresTable tbody');
    const finalizarRegistroBtn = document.getElementById('finalizarRegistroBtn');

    let currentStep = 1;
    const formSteps = document.querySelectorAll('.form-step');
    let participants = []; // Array to store participants for the current exhibitor

    // Function to show a specific form step
    function showStep(step) {
        formSteps.forEach((formStep, index) => {
            formStep.classList.toggle('active', index + 1 === step);
        });
        currentStep = step;
    }

    // Initial display
    showStep(currentStep);

    // Navigation buttons
    document.querySelectorAll('.next-step-btn').forEach(button => {
        button.addEventListener('click', () => {
            if (currentStep < formSteps.length) {
                showStep(currentStep + 1);
            }
        });
    });

    document.querySelectorAll('.prev-step-btn').forEach(button => {
        button.addEventListener('click', () => {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        });
    });

    // Add Participant
    participanteForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const nombreCompleto = document.getElementById('participantNombreCompleto').value;
        const cargoPuesto = document.getElementById('participantCargoPuesto').value;

        if (nombreCompleto && cargoPuesto) {
            const newParticipant = {
                nombre_completo: nombreCompleto,
                cargo_puesto: cargoPuesto
            };
            participants.push(newParticipant);
            renderParticipants();
            participanteForm.reset();
        } else {
            alert('Por favor, complete todos los campos del participante.');
        }
    });

    // Render Participants in the table
    function renderParticipants() {
        participantsTableBody.innerHTML = '';
        participants.forEach((participant, index) => {
            const row = participantsTableBody.insertRow();
            row.insertCell(0).textContent = participant.nombre_completo;
            row.insertCell(1).textContent = participant.cargo_puesto;
            const actionsCell = row.insertCell(2);
            const deleteButton = document.createElement('button');
            deleteButton.textContent = 'Eliminar';
            deleteButton.classList.add('delete-btn');
            deleteButton.addEventListener('click', () => {
                participants.splice(index, 1);
                renderParticipants();
            });
            actionsCell.appendChild(deleteButton);
        });
    }

    // Finalize Registration (Submit Expositor and Participants)
    finalizarRegistroBtn.addEventListener('click', async () => {
        const formData = new FormData(expositorForm);
        formData.append('participantes', JSON.stringify(participants));

        try {
            const response = await fetch('api/expositores.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.message);
                expositorForm.reset();
                participanteForm.reset();
                participants = [];
                renderParticipants();
                showStep(1); // Go back to the first step
                loadExpositores(); // Reload expositor list
            } else {
                alert('Error al registrar expositor: ' + (result.message || JSON.stringify(result.errors)));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error al conectar con el servidor.');
        }
    });

    // Load Expositors
    async function loadExpositores() {
        try {
            const response = await fetch('api/expositores.php');
            const result = await response.json();

            if (response.ok) {
                expositoresTableBody.innerHTML = '';
                result.forEach(expositor => {
                    const row = expositoresTableBody.insertRow();
                    row.insertCell(0).textContent = expositor.id;
                    row.insertCell(1).textContent = expositor.nombre;
                    row.insertCell(2).textContent = expositor.apellido;
                    row.insertCell(3).textContent = expositor.correo;
                    row.insertCell(4).textContent = expositor.razon_social;
                    const actionsCell = row.insertCell(5);

                    const editButton = document.createElement('button');
                    editButton.textContent = 'Editar';
                    editButton.classList.add('edit-btn');
                    editButton.addEventListener('click', () => editExpositor(expositor.id));
                    actionsCell.appendChild(editButton);

                    const deleteButton = document.createElement('button');
                    deleteButton.textContent = 'Eliminar';
                    deleteButton.classList.add('delete-btn');
                    deleteButton.addEventListener('click', () => deleteExpositor(expositor.id));
                    actionsCell.appendChild(deleteButton);
                });
            } else {
                console.error('Error al cargar expositores:', result.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Edit Expositor (Placeholder - needs full implementation)
    async function editExpositor(id) {
        alert('Funcionalidad de edición para expositor ID: ' + id + ' (no implementada completamente)');
        // In a real application, you would fetch the expositor data,
        // populate the form, and change the form's submit behavior to an update.
    }

    // Delete Expositor
    async function deleteExpositor(id) {
        if (confirm('¿Está seguro de que desea eliminar este expositor?')) {
            try {
                const response = await fetch(`api/expositores.php?id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message);
                    loadExpositores(); // Reload the list
                } else {
                    alert('Error al eliminar expositor: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Ocurrió un error al conectar con el servidor.');
            }
        }
    }

    // Initial load of expositors
    loadExpositores();
});
