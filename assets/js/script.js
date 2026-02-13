document.addEventListener('DOMContentLoaded', () => {
    const expositorForm = document.getElementById('expositorForm');
    const mamparaCheckbox = document.getElementById('mampara');
    const rotuloAntepechoGroup = document.getElementById('rotulo_group');
    const manualExpositorLink = document.getElementById('manualExpositorLink');
    const hojaResponsivaTemplateLink = document.getElementById('hojaResponsivaTemplateLink');
    const logoInput = document.getElementById('logo');
    const logoLabel = document.getElementById('logo-name');
    const hojaResponsivaInput = document.getElementById('hoja_responsiva');
    const hojaResponsivaName = document.getElementById('hoja-responsiva-name');

    // Update custom file input labels
    logoInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            logoLabel.textContent = this.files[0].name;
        } else {
            logoLabel.textContent = 'Seleccionar archivo';
        }
    });

    hojaResponsivaInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            hojaResponsivaName.textContent = this.files[0].name;
        } else {
            hojaResponsivaName.textContent = 'Ningún archivo seleccionado';
        }
    });

    // Helper function to display errors
    function displayError(fieldId, message) {
        const errorElement = document.getElementById(`${fieldId}-error`);
        const inputElement = document.getElementById(fieldId);
        if (errorElement && inputElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            inputElement.classList.add('is-invalid');
        }
    }

    // Helper function to clear all errors
    function clearErrors() {
        document.querySelectorAll('.error-message').forEach(errorElement => {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        });
        document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(inputElement => {
            inputElement.classList.remove('is-invalid');
        });
    }

    // Toggle 'Rótulo del Antepecho' field visibility
    mamparaCheckbox.addEventListener('change', () => {
        if (mamparaCheckbox.checked) {
            rotuloAntepechoGroup.style.display = 'block';
        } else {
            rotuloAntepechoGroup.style.display = 'none';
        }
    });

    // No longer handling form submission here, as it's a multi-step wizard
    // The final submission will be handled by handleFinalRegistration()
    // expositorForm.addEventListener('submit', async (e) => {
    //     e.preventDefault();
    //     clearErrors(); // Clear previous errors

    //     const formData = new FormData(expositorForm);
    //     // Convert mampara checkbox value to boolean
    //     formData.set('mampara', mamparaCheckbox.checked ? '1' : '0');

    //     try {
    //         const response = await fetch('api/expositores.php', {
    //             method: 'POST',
    //             body: formData
    //         });

    //         const result = await response.json();

    //         if (response.ok) {
    //             alert(result.message);
    //             expositorForm.reset();
    //             rotuloAntepechoGroup.style.display = 'none'; // Hide again after reset
    //             clearErrors(); // Clear errors on successful submission

    //             // After successful exhibitor registration, show participant management
    //             const expositorId = result.expositor_id; // Assuming the API returns the new exhibitor_id
    //             if (expositorId) {
    //                 document.getElementById('expositor_id_participant').value = expositorId;
    //                 document.getElementById('participant-management').style.display = 'block';
    //                 loadParticipants(expositorId);
    //             }
    //         } else {
    //             if (result.errors) {
    //                 // Display field-specific errors
    //                 for (const field in result.errors) {
    //                     displayError(field, result.errors[field]);
    //                 }
    //             } else {
    //                 alert('Error: ' + result.message);
    //             }
    //         }
    //     } catch (error) {
    //         console.error('Error submitting form:', error);
    //         alert('An error occurred while submitting the form.');
    //     }
    // });

    // Get references to participant management elements
    const participantForm = document.getElementById('participantForm');
    const participantsTableBody = document.querySelector('#participantsTable tbody');
    const saveParticipantBtn = document.getElementById('saveParticipantBtn');
    const cancelEditParticipantBtn = document.getElementById('cancelEditParticipantBtn');
    const participantIdField = document.getElementById('participantId');

    const expositorIdParticipantField = document.getElementById('expositor_id_participant');
    const nombreCompletoParticipantField = document.getElementById('participantName');

    let participants = []; // Array to store participant data temporarily

    // Function to render participants in the table
    function renderParticipants() {
        participantsTableBody.innerHTML = ''; // Clear existing rows
        participants.forEach((participant, index) => {
            const row = participantsTableBody.insertRow();
            row.setAttribute('data-id', participant.id); // Use a temporary ID or index for frontend management

            const nombreCell = row.insertCell(0);
            nombreCell.textContent = participant.nombre_completo;
            nombreCell.setAttribute('data-label', 'Nombre Completo:');

            const cargoCell = row.insertCell(1);
            cargoCell.textContent = participant.cargo_puesto;
            cargoCell.setAttribute('data-label', 'Cargo/Puesto:');

            const actionsCell = row.insertCell(2);
            const editButton = document.createElement('button');
            editButton.textContent = 'Editar';
            editButton.classList.add('edit-btn');
            editButton.addEventListener('click', () => editParticipant(index));
            actionsCell.appendChild(editButton);

            const deleteButton = document.createElement('button');
            deleteButton.textContent = 'Eliminar';
            deleteButton.classList.add('delete-btn');
            deleteButton.addEventListener('click', () => deleteParticipant(index));
            actionsCell.appendChild(deleteButton);
        });
    }

    // Function to handle participant form submission (add/edit)
    participantForm.addEventListener('submit', (e) => {
        e.preventDefault();
        clearErrors(); // Clear previous errors

        const newParticipant = {
            id: participantIdField.value || Date.now(), // Use timestamp as temporary ID
            nombre_completo: nombreCompletoParticipantField.value,
            cargo_puesto: document.getElementById('participantRole').value,
        };

        if (participantIdField.value) {
            // Editing existing participant
            const index = participants.findIndex(p => p.id == participantIdField.value);
            if (index !== -1) {
                participants[index] = newParticipant;
            }
        } else {
            // Adding new participant
            participants.push(newParticipant);
        }

        participantForm.reset();
        participantIdField.value = '';
        saveParticipantBtn.textContent = 'Añadir';
        cancelEditParticipantBtn.style.display = 'none';
        renderParticipants(); // Re-render the table
    });

    // Function to populate form for editing
    function editParticipant(index) {
        const participant = participants[index];
        participantIdField.value = participant.id;
        nombreCompletoParticipantField.value = participant.nombre_completo;
        document.getElementById('participantRole').value = participant.cargo_puesto;

        saveParticipantBtn.textContent = 'Guardar Cambios';
        cancelEditParticipantBtn.style.display = 'inline-block';
    }

    // Function to delete a participant
    function deleteParticipant(index) {
        if (!confirm('¿Está seguro de que desea eliminar este participante?')) {
            return;
        }
        participants.splice(index, 1); // Remove from array
        renderParticipants(); // Re-render the table
    }

    // Cancel edit mode
    cancelEditParticipantBtn.addEventListener('click', () => {
        participantForm.reset();
        participantIdField.value = '';
        saveParticipantBtn.textContent = 'Añadir';
        cancelEditParticipantBtn.style.display = 'none';
    });

    // Set document download links
    // Assuming 'manual_expositor.pdf' and 'hoja_responsiva_template.pdf' are the filenames
    // and they are located in assets/documents/
    manualExpositorLink.href = 'api/documents.php?file=manual_expositor.pdf';
    hojaResponsivaTemplateLink.href = 'api/documents.php?file=hoja_responsiva_template.pdf';

    // Collapsible sections functionality
    const collapsibleHeaders = document.querySelectorAll('.collapsible-header');
    const progressSteps = document.querySelectorAll('.progress-steps .step');

    // Wizard functionality
    const formSteps = document.querySelectorAll('.form-step');
    let currentStep = 0;

    function showStep(stepIndex) {
        formSteps.forEach((step, index) => {
            if (index === stepIndex) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
        
        // Update progress bar
        const progressFill = document.getElementById('progress-fill');
        const steps = document.querySelectorAll('.progress-steps .step');
        const progress = (stepIndex / (formSteps.length - 1)) * 100;
        if (progressFill) progressFill.style.width = `${progress}%`;
        
        steps.forEach((step, index) => {
            if (index <= stepIndex) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }

    function goToNextStep() {
        if (currentStep < formSteps.length - 1) {
            currentStep++;
            showStep(currentStep);
        }
    }

    function goToPrevStep() {
        if (currentStep > 0) {
            currentStep--;
            showStep(currentStep);
        }
    }

    // Handle navigation buttons
    document.querySelectorAll('.next-step-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // If it's the last step, handle final registration
            if (currentStep === formSteps.length - 1) {
                handleFinalRegistration();
                return;
            }
            
            // If it's step 1, just move forward
            if (currentStep === 0) {
                goToNextStep();
                return;
            }

            // If it's step 2, move to step 3
            if (currentStep === 1) {
                goToNextStep();
                return;
            }

            // If it's step 3, move to step 4
            if (currentStep === 2) {
                goToNextStep();
                return;
            }
        });
    });

    document.querySelectorAll('.prev-step-btn').forEach(btn => {
        btn.addEventListener('click', goToPrevStep);
    });

    // Initial display
    showStep(currentStep);

    async function handleFinalRegistration() {
        if (confirm('¿Está seguro de que desea finalizar el registro?')) {
            // Collect all data
            const formData = new FormData(expositorForm);
            
            // Step 2 data
            const hojaResponsiva = document.getElementById('hoja_responsiva').files[0];
            if (hojaResponsiva) formData.append('hoja_responsiva', hojaResponsiva);
            
            formData.set('mampara', mamparaCheckbox.checked ? '1' : '0');
            formData.set('rotulo_antepecho', document.getElementById('rotulo_antepecho').value);

            // Append participants data
            formData.append('participantes', JSON.stringify(participants));

            try {
                // 1. Create Expositor
                const response = await fetch('api/expositores.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok) {
                    const expositorId = result.expositor_id;
                    
                    // Here you could also link participants if they weren't linked already
                    
                    alert('¡Registro finalizado con éxito!');
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.error || result.message));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar el registro.');
            }
        }
    }
});

