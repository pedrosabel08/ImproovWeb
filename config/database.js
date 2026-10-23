const path = require('node:path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

function requiredEnv(key) {
    const value = process.env[key];
    if (value === undefined || value === '') {
        throw new Error(`Variavel de ambiente obrigatoria ausente: ${key}`);
    }
    return value;
}

function databaseConfig() {
    const portValue = process.env.DB_PORT ?? '3306';
    const port = Number(portValue);
    if (!/^\d+$/.test(portValue) || !Number.isInteger(port) || port < 1 || port > 65535) {
        throw new Error('Variavel de ambiente invalida: DB_PORT');
    }

    return {
        host: requiredEnv('DB_HOST'),
        port,
        user: requiredEnv('DB_USERNAME'),
        password: requiredEnv('DB_PASSWORD'),
        database: requiredEnv('DB_DATABASE'),
        charset: 'utf8mb4',
    };
}

module.exports = { databaseConfig };
