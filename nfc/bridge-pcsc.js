const { NFC } = require('nfc-pcsc');
const WebSocket = require('ws');

const nfc = new NFC(); // Utiliza el servicio Smart Card de Windows
const wss = new WebSocket.Server({ port: 3000 });

console.log('--- NFC PCSC Bridge (Driver Oficial) ---');
console.log('Esperando conexiones en ws://localhost:3000...');

let activeClient = null;

wss.on('connection', (ws) => {
    console.log('Cliente conectado desde el navegador.');
    activeClient = ws;
    ws.send(JSON.stringify({ type: 'status', message: 'Conectado al Bridge PCSC (Driver Oficial).' }));

    ws.on('close', () => {
        console.log('Cliente desconectado.');
        activeClient = null;
    });
});

function broadcast(data) {
    if (activeClient && activeClient.readyState === WebSocket.OPEN) {
        activeClient.send(JSON.stringify(data));
    }
}

nfc.on('reader', reader => {
    console.log(`Lector detectado: ${reader.name}`);
    broadcast({ type: 'reader_connected', name: reader.name });

    reader.on('card', card => {
        // card.uid ya viene formateado o en buffer
        const uid = card.uid.toUpperCase();
        console.log(`Tarjeta detectada: ${uid}`);
        broadcast({ 
            type: 'tag_detected', 
            uid: uid, 
            standard: card.standard || 'Desconocido'
        });
    });

    reader.on('card.off', card => {
        console.log(`Tarjeta removida.`);
        broadcast({ type: 'tag_removed' });
    });

    reader.on('error', err => {
        console.error(`Error en el lector: ${err.message}`);
        broadcast({ type: 'error', message: err.message });
    });

    reader.on('end', () => {
        console.log(`Lector ${reader.name} desconectado.`);
        broadcast({ type: 'reader_disconnected' });
    });
});

nfc.on('error', err => {
    console.error(`Error de PCSC: ${err.message}. Asegúrate de que el servicio 'Tarjeta Inteligente' esté corriendo.`);
});
