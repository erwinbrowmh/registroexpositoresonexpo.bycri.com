const { usb } = require('usb');
const WebSocket = require('ws');

const wss = new WebSocket.Server({ port: 3000 });
const VENDOR_ID = 0x072f;
const PRODUCT_ID = 0x2200;

console.log('--- NFC USB Bridge for ACR122U ---');
console.log('Esperando conexiones en ws://localhost:3000...');

let activeClient = null;
let device = null;
let endpointIn = null;
let endpointOut = null;
let sequence = 0;

wss.on('connection', (ws) => {
    console.log('Cliente conectado desde el navegador.');
    activeClient = ws;
    ws.send(JSON.stringify({ type: 'status', message: 'Conectado al Bridge USB local.' }));
    
    // Auto-attempt connection to device when client connects
    tryConnectDevice();

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

async function tryConnectDevice() {
    try {
        device = usb.findByIds(VENDOR_ID, PRODUCT_ID);
        if (!device) {
            console.log('Lector ACR122U no encontrado. Conéctalo al puerto USB.');
            return;
        }

        device.open();
        const iface = device.interfaces[0];
        
        if (iface.isKernelDriverActive()) {
            iface.detachKernelDriver();
        }

        iface.claim();
        
        endpointIn = iface.endpoints.find(e => e.direction === 'in');
        endpointOut = iface.endpoints.find(e => e.direction === 'out');

        console.log('Lector ACR122U conectado y listo.');
        broadcast({ type: 'reader_connected', name: 'ACR122U USB' });

        // Start polling for cards
        startPolling();

    } catch (err) {
        console.error('Error al conectar con el dispositivo:', err.message);
        broadcast({ type: 'error', message: err.message });
    }
}

async function transmit(apdu) {
    if (!device || !endpointOut || !endpointIn) return null;

    const header = Buffer.alloc(10);
    header[0] = 0x6f; // CCID_XFR_BLOCK
    header.writeUInt32LE(apdu.length, 1);
    header[5] = 0x00; // Slot
    header[6] = sequence++;
    header[7] = 0x00;
    header[8] = 0x00;
    header[9] = 0x00;

    const packet = Buffer.concat([header, apdu]);

    return new Promise((resolve, reject) => {
        endpointOut.transfer(packet, (err) => {
            if (err) return reject(err);
            
            endpointIn.transfer(64, (err, data) => {
                if (err) return reject(err);
                // Return payload (skip 10 bytes header)
                resolve(data.slice(10));
            });
        });
    });
}

async function startPolling() {
    console.log('Iniciando escaneo de tarjetas...');
    const getUidApdu = Buffer.from([0xFF, 0xCA, 0x00, 0x00, 0x00]);
    let lastUid = null;

    while (device) {
        try {
            const response = await transmit(getUidApdu);
            if (response && response.length >= 2) {
                const sw1 = response[response.length - 2];
                const sw2 = response[response.length - 1];

                if (sw1 === 0x90 && sw2 === 0x00) {
                    const uid = response.slice(0, response.length - 2).toString('hex').toUpperCase();
                    if (uid !== lastUid) {
                        console.log(`Tarjeta detectada: ${uid}`);
                        broadcast({ type: 'tag_detected', uid: uid });
                        lastUid = uid;
                    }
                } else {
                    if (lastUid !== null) {
                        console.log('Tarjeta removida.');
                        broadcast({ type: 'tag_removed' });
                        lastUid = null;
                    }
                }
            }
        } catch (err) {
            console.error('Error en polling:', err.message);
            break;
        }
        await new Promise(r => setTimeout(r, 500));
    }
}

// Watch for device detachment
usb.on('detach', (dev) => {
    if (dev.deviceDescriptor.idVendor === VENDOR_ID && dev.deviceDescriptor.idProduct === PRODUCT_ID) {
        console.log('Lector desconectado.');
        broadcast({ type: 'reader_disconnected' });
        device = null;
    }
});
