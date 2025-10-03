const assert = require('assert');

function filterValidFiles(files) {
    return files.filter(file => file.size > 0);
}

test('filterValidFiles should exclude empty files', () => {
    const files = [
        { name: 'file1.txt', size: 100 },
        { name: 'file2.txt', size: 0 },
        { name: 'file3.txt', size: 50 },
    ];
    const result = filterValidFiles(files);
    assert.strictEqual(result.length, 2);
    assert.strictEqual(result[0].name, 'file1.txt');
    assert.strictEqual(result[1].name, 'file3.txt');
});

function removeEmptyFilesFromFormData(formData) {
    formData._entries = formData._entries.filter(([key, value]) => {
        // Checa se tem 'size' e se é 0
        return !(value && typeof value.size === 'number' && value.size === 0);
    });
    return formData;
}


test('removeEmptyFilesFromFormData should remove empty files from FormData', () => {
    // Mock File class
    class MockFile {
        constructor(name, size) {
            this.name = name;
            this.size = size;
        }
    }
    // Mock FormData
    const entries = [
        ['arquivos_por_imagem[1][]', new MockFile('file1.txt', 0)],
        ['arquivos_por_imagem[2][]', new MockFile('file2.txt', 100)],
        ['arquivos_por_imagem[3][]', new MockFile('file3.txt', 0)],
        ['arquivos_por_imagem[4][]', new MockFile('file4.txt', 50)],
        ['descricao', 'Teste']
    ];
    const mockFormData = {
        _entries: [...entries],
        entries() { return this._entries[Symbol.iterator](); },
        delete(key) { this._entries = this._entries.filter(([k]) => k !== key); }
    };

    const cleanedFormData = removeEmptyFilesFromFormData(mockFormData);

    const resultKeys = cleanedFormData._entries.map(([k]) => k);
    expect(resultKeys).toContain('arquivos_por_imagem[2][]');
    expect(resultKeys).toContain('arquivos_por_imagem[4][]');
    expect(resultKeys).toContain('descricao');
    expect(resultKeys).not.toContain('arquivos_por_imagem[1][]');
    expect(resultKeys).not.toContain('arquivos_por_imagem[3][]');
});

test('should not remove non-file entries from FormData', () => {
    class MockFile {
        constructor(name, size) {
            this.name = name;
            this.size = size;
        }
    }
    const entries = [
        ['descricao', 'Teste'],
        ['tipo_arquivo', 'SKP'],
        ['arquivos_por_imagem[1][]', new MockFile('file1.txt', 0)],
    ];
    const mockFormData = {
        _entries: [...entries],
        entries() { return this._entries[Symbol.iterator](); },
        delete(key) { this._entries = this._entries.filter(([k]) => k !== key); }
    };

    const cleanedFormData = removeEmptyFilesFromFormData(mockFormData);

    const resultKeys = cleanedFormData._entries.map(([k]) => k);
    expect(resultKeys).toContain('descricao');
    expect(resultKeys).toContain('tipo_arquivo');
    expect(resultKeys).not.toContain('arquivos_por_imagem[1][]');
});