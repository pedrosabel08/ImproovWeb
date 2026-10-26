const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Stub dotenv to test without installed dependencies or production credentials.
const fixture = {
    DB_HOST: 'database.example.invalid',
    DB_PORT: '3307',
    DB_USERNAME: 'fixture_user',
    DB_PASSWORD: 'fixture$#=password',
    DB_DATABASE: 'fixture_database',
};
const sandbox = {
    __dirname: path.join(__dirname, '..', 'config'),
    process: { env: { ...fixture } },
    module: { exports: {} },
    require(name) {
        if (name === 'node:path') return path;
        assert.equal(name, 'dotenv');
        return {
            config(options) {
                assert.equal(options.path, path.resolve(__dirname, '..', '.env'));
            },
        };
    },
};
vm.runInNewContext(fs.readFileSync(path.join(sandbox.__dirname, 'database.js'), 'utf8'), sandbox);
const { databaseConfig } = sandbox.module.exports;
const config = databaseConfig();
assert.equal(config.host, fixture.DB_HOST);
assert.equal(config.user, fixture.DB_USERNAME);
assert.equal(config.password, fixture.DB_PASSWORD);
assert.equal(config.database, fixture.DB_DATABASE);
assert.equal(config.port, 3307);
assert.equal(config.charset, 'utf8mb4');

delete sandbox.process.env.DB_PORT;
assert.equal(databaseConfig().port, 3306);
for (const port of ['', '0', '65536', 'invalid', '3e3']) {
    sandbox.process.env.DB_PORT = port;
    assert.throws(databaseConfig, /DB_PORT/);
}
sandbox.process.env.DB_PORT = '3306';
for (const key of ['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE']) {
    delete sandbox.process.env[key];
    assert.throws(databaseConfig, new RegExp(key));
    sandbox.process.env[key] = '';
    assert.throws(databaseConfig, new RegExp(key));
    sandbox.process.env[key] = fixture[key];
}

console.log('Database configuration checks passed.');
