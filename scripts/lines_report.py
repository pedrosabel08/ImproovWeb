#!/usr/bin/env python3
"""
Gera relatório de contagem de linhas por arquivo e por módulo (top-level folder).
Ignora arquivos listados pelo Git (.gitignore) se o diretório for um repositório Git.

Uso:
  python scripts/lines_report.py

Saída: impressa no stdout; redirecione para arquivo se desejar.
"""
import os
import subprocess
import sys
from collections import defaultdict
from datetime import datetime


def repo_root():
    # script está em scripts/; repo root é um nível acima
    return os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


def git_list_files(root):
    try:
        # verifica se estamos dentro de um work tree
        p = subprocess.run(['git', 'rev-parse', '--is-inside-work-tree'], cwd=root, capture_output=True, text=True)
        if p.returncode != 0 or p.stdout.strip() != 'true':
            return None
        p2 = subprocess.run(['git', 'ls-files', '--cached', '--others', '--exclude-standard'], cwd=root, capture_output=True, text=True)
        if p2.returncode != 0:
            return None
        files = [line.strip() for line in p2.stdout.splitlines() if line.strip()]
        return files
    except FileNotFoundError:
        return None


def parse_gitignore(root):
    path = os.path.join(root, '.gitignore')
    patterns = []
    if not os.path.exists(path):
        return patterns
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        for ln in f:
            ln = ln.strip()
            if not ln or ln.startswith('#'):
                continue
            patterns.append(ln)
    return patterns


def match_simple_patterns(relpath, patterns):
    # fallback simples: usa fnmatch para casamentos básicos e prefixo para diretórios
    import fnmatch
    for p in patterns:
        if p.endswith('/'):
            if relpath.startswith(p.rstrip('/')):
                return True
        # tratar padrão com /** ou /* simplificado
        if fnmatch.fnmatch(relpath, p) or fnmatch.fnmatch(relpath, os.path.join(p, '*')):
            return True
    return False


def fallback_walk(root):
    patterns = parse_gitignore(root)
    files = []
    for dirpath, dirnames, filenames in os.walk(root):
        # pular .git
        rel_dir = os.path.relpath(dirpath, root)
        if rel_dir == '.':
            rel_dir = ''
        if rel_dir.split(os.sep)[0] == '.git':
            continue
        for fn in filenames:
            rel = os.path.normpath(os.path.join(rel_dir, fn)).lstrip('.' + os.sep)
            if rel.startswith('.git'):
                continue
            if patterns and match_simple_patterns(rel, patterns):
                continue
            files.append(rel)
    return files


def count_lines(fullpath):
    try:
        with open(fullpath, 'rb') as f:
            # contar linhas lendo por iterador (funciona mesmo em binários)
            return sum(1 for _ in f)
    except Exception:
        return 0


def main():
    root = repo_root()
    files = git_list_files(root)
    if files is None:
        files = fallback_walk(root)

    # filtrar arquivos: garantir que existam, e excluir tudo dentro de Backup/ e imagens .jpg/.jpeg
    filtered_files = []
    for f in files:
        fullp = os.path.join(root, f)
        if not os.path.isfile(fullp):
            continue
        parts = f.split(os.sep)
        if parts and parts[0].lower() == 'backup':
            continue
        low = f.lower()
        if low.endswith('.jpg') or low.endswith('.jpeg'):
            continue
        filtered_files.append(f)

    module_stats = defaultdict(lambda: {'lines': 0, 'files': 0, 'file_list': []})
    total_lines = 0
    total_files = 0

    for rel in filtered_files:
        full = os.path.join(root, rel)
        lines = count_lines(full)
        total_lines += lines
        total_files += 1
        parts = rel.split(os.sep)
        module = parts[0] if len(parts) > 1 else (parts[0] if parts[0] else 'root')
        module_stats[module]['lines'] += lines
        module_stats[module]['files'] += 1
        module_stats[module]['file_list'].append((rel, lines))

    # ranking por linhas
    ranking = sorted(module_stats.items(), key=lambda kv: kv[1]['lines'], reverse=True)

    out_lines = []
    out_lines.append('Relatório de linhas por arquivo e por módulo')
    out_lines.append(f'Gerado em: {datetime.now().isoformat()}')
    out_lines.append(f'Raiz do repositório: {root}')
    out_lines.append(f'Arquivos considerados (ignora .gitignore quando há Git): {len(filtered_files)}')
    out_lines.append(f'Total de arquivos: {total_files}  Total de linhas: {total_lines}')
    out_lines.append('')
    out_lines.append('Ranking de módulos por linhas:')
    for i, (mod, stats) in enumerate(ranking, 1):
        out_lines.append(f"{i:2d}. {mod:30s} — {stats['lines']:8d} linhas  / {stats['files']:5d} arquivos")

    out_lines.append('')
    out_lines.append('Top 10 arquivos por linhas (global):')
    all_files = []
    for mod, s in module_stats.items():
        all_files.extend(s['file_list'])
    all_files.sort(key=lambda t: t[1], reverse=True)
    for rel, ln in all_files[:10]:
        out_lines.append(f"{ln:8d}  {rel}")

    # grava em arquivo na raiz do repositório
    outpath = os.path.join(root, 'lines_report.txt')
    try:
        with open(outpath, 'w', encoding='utf-8') as f:
            f.write('\n'.join(out_lines))
        print(f'Relatório gravado em: {outpath}')
    except Exception as e:
        print('Erro ao gravar relatório:', e)


if __name__ == '__main__':
    main()
