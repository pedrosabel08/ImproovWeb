<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV — Produção por Função</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- ── Cabeçalho ─────────────────────────────────────────── -->
    <header class="tv-header">
        <div class="tv-header-left">
            <img src="../gif/assinatura_branco.gif" alt="Improov" class="tv-logo">
        </div>
        <div class="tv-header-center">
            <h1 class="tv-title">Produção por Função</h1>
            <span id="tvPeriodo" class="tv-subtitle"></span>
        </div>
        <div class="tv-header-right">
            <div id="tvRelogio" class="tv-clock"></div>
            <div id="tvLastUpdate" class="tv-last-update">Carregando...</div>
        </div>
    </header>

    <!-- ── Corpo principal ───────────────────────────────────── -->
    <main class="tv-main">

        <!-- Gráfico de barras horizontais -->
        <section class="tv-chart-section">
            <div class="tv-chart-wrap">
                <canvas id="tvChart" aria-label="Gráfico de produção por função"></canvas>
            </div>
        </section>

        <!-- Tabela / legenda -->
        <section class="tv-table-section">
            <table class="tv-table" id="tvTable">
                <thead>
                    <tr>
                        <th>Função</th>
                        <th>Qtd</th>
                        <th>Meta</th>
                        <th>%</th>
                        <th>Anterior</th>
                    </tr>
                </thead>
                <tbody id="tvTableBody">
                    <tr>
                        <td colspan="5" class="tv-table-loading">Carregando...</td>
                    </tr>
                </tbody>
            </table>

            <!-- Legenda de badges -->
            <div class="tv-legend">
                <span class="tv-legend-item tv-legend-low"><i class="fa-solid fa-circle"></i> Abaixo de 50%</span>
                <span class="tv-legend-item tv-legend-mid"><i class="fa-solid fa-circle"></i> 50 – 80%</span>
                <span class="tv-legend-item tv-legend-high"><i class="fa-solid fa-circle"></i> Acima de 80%</span>
                <span class="tv-legend-item tv-legend-done"><i class="fa-solid fa-circle"></i> Meta atingida</span>
                <span class="tv-legend-item tv-legend-record"><i class="fa-solid fa-trophy"></i> Recorde</span>
            </div>
        </section>

    </main>

    <!-- Overlay de reconexão -->
    <div id="tvOffline" class="tv-offline" style="display:none">
        <i class="fa-solid fa-wifi-slash"></i>
        <span>Sem conexão — tentando reconectar...</span>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</body>

</html>