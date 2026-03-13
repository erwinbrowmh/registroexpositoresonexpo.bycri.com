let socket = null;

function addLog(msg) {
    const box = document.getElementById('log-box');
    box.innerHTML += `<div>[${new Date().toLocaleTimeString()}] ${msg}</div>`;
    box.scrollTop = box.scrollHeight;
}

function handleMessage(d) {
    console.log("Mensaje recibido:", d); // Debug
    if (d.type === 'raw_dump') {
        // Actualizar UI básica
        document.getElementById('lbl-uid').textContent = d.uid || '--';
        document.getElementById('lbl-chip').textContent = d.chip || '--';
        document.getElementById('lbl-pages').textContent = d.pages || '--';
        
        // Manejo robusto del texto recuperado
        const textLabel = document.getElementById('lbl-text');
        if (d.text && d.text.trim().length > 0) {
            textLabel.textContent = d.text;
            textLabel.style.color = '#28a745'; // Verde para éxito
            textLabel.style.fontWeight = 'bold';
        } else {
            textLabel.textContent = '-- (Sin mensaje legible)';
            textLabel.style.color = '#6c757d'; // Gris por defecto
            textLabel.style.fontWeight = 'normal';
        }
        
        // Dibujar mapa de memoria
        if (d.blocks) {
            drawMap(d.blocks);
        }
    } else if (d.type === 'success') {
        $('#modalWait').modal('hide');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        // Usar SweetAlert o un toast sería mejor, pero mantenemos alert por ahora
        setTimeout(() => { alert("✅ " + d.message); }, 100);
        addLog("Éxito: " + d.message);
    } else if (d.type === 'error') {
        $('#modalWait').modal('hide');
        $('.modal-backdrop').remove();
        alert("❌ Error: " + d.message);
        addLog("Error: " + d.message);
    } else if (d.type === 'tag_removed') {
        document.getElementById('memoryContainer').innerHTML =
            '<div class="text-center py-5 text-muted"><i class="fas fa-id-card fa-3x mb-3"></i><br>Acerque una tarjeta...</div>';
        document.getElementById('lbl-text').textContent = '--';
        document.getElementById('lbl-uid').textContent = '--';
    } else if (d.type === 'status') {
        addLog(d.message);
    }
}

document.getElementById('btnConnect').onclick = () => {
    socket = new WebSocket('ws://localhost:3000');
    
    socket.onopen = () => { 
        addLog("✅ Bridge Conectado"); 
        document.getElementById('mainView').style.display='block'; 
        document.getElementById('btnConnect').classList.remove('btn-primary');
        document.getElementById('btnConnect').classList.add('btn-success');
        document.getElementById('btnConnect').textContent = 'Conectado';
        document.getElementById('btnConnect').disabled = true;
    };
    
    socket.onmessage = (e) => {
        try {
            const d = JSON.parse(e.data);
            handleMessage(d);
        } catch (err) {
            console.error("Error parseando JSON:", err);
        }
    };
    
    socket.onerror = (e) => {
        addLog("❌ Error de conexión con Bridge");
        console.error(e);
    };
    
    socket.onclose = () => {
        addLog("⚠️ Desconectado del Bridge");
        document.getElementById('btnConnect').classList.remove('btn-success');
        document.getElementById('btnConnect').classList.add('btn-primary');
        document.getElementById('btnConnect').textContent = 'Conectar Bridge';
        document.getElementById('btnConnect').disabled = false;
    };
};

function drawMap(blocks) {
    let html = '<div class="table-responsive"><table class="table table-sm table-hover table-bordered" style="font-family: monospace; font-size: 0.85rem;">';
    html += '<thead class="thead-light"><tr><th style="width: 50px;">Pág</th><th>Hexadecimal (4 Bytes)</th><th>ASCII</th></tr></thead><tbody>';
    
    blocks.forEach((b, i) => {
        // b viene como "XX XX XX XX"
        const bytes = b.split(' ');
        let asciiStr = "";
        
        bytes.forEach(hex => {
            const code = parseInt(hex, 16);
            // Caracteres imprimibles seguros
            if (code >= 32 && code <= 126) {
                asciiStr += String.fromCharCode(code);
            } else {
                asciiStr += '<span style="color:#ccc;">.</span>';
            }
        });

        // Colores semánticos según la especificación NTAG
        let rowStyle = "";
        let pageType = "Datos";
        
        if (i < 4) {
            // Header (UID, Serial, Static Lock, CC)
            rowStyle = "background-color: #e8f4f8;"; 
            pageType = "Sistema";
        } else if (i >= blocks.length - 5) {
            // Configuración dinámica y PWD (aprox, depende del chip)
            rowStyle = "background-color: #fff3cd;";
            pageType = "Config";
        }

        html += `<tr style="${rowStyle}">
            <td class="text-center text-muted">${i}</td>
            <td style="letter-spacing: 1px; font-weight: bold; color: #333;">${b}</td>
            <td style="color: #007bff;">${asciiStr}</td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('memoryContainer').innerHTML = html;
}

document.getElementById('btnWrite').onclick = () => {
    const val = document.getElementById('txtInput').value;
    if(!val) return alert("Ingresa un texto para grabar.");
    
    if (socket && socket.readyState === WebSocket.OPEN) {
        document.getElementById('waitTitle').innerHTML = 
            '<i class="fas fa-arrow-down mb-2"></i><br>Acerque la tarjeta al lector<br><small>para grabar la información</small>';
        $('#modalWait').modal('show');
        socket.send(JSON.stringify({type:'write_request', text: val}));
    } else {
        alert("El Bridge no está conectado.");
    }
};

document.getElementById('btnReset').onclick = () => {
    if(!confirm("¿Estás seguro de restablecer el chip? Se borrará todo y será compatible con móviles.")) return;
    
    if (socket && socket.readyState === WebSocket.OPEN) {
        document.getElementById('waitTitle').innerHTML = 
            '<i class="fas fa-arrow-down mb-2"></i><br>Acerque la tarjeta al lector<br><small>para reiniciar el chip</small>';
        $('#modalWait').modal('show');
        socket.send(JSON.stringify({type:'reset_request'}));
    } else {
        alert("El Bridge no está conectado.");
    }
};
