<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Flow Animado</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
</head>

<body class="bg-[#f6f6f0] font-sans">

    <!-- Container principal -->
    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Sidebar -->
        <aside
            class="bg-white w-full lg:w-20 shadow-lg flex lg:flex-col items-center p-4 gap-6 animate__animated animate__fadeInLeft">
            <button class="hover:scale-110 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-800" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
                </svg>
            </button>
            <button class="hover:scale-110 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-800" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.105 0-2 .895-2 2v6a2 2 0 002 2h8a2 2 0 002-2v-6c0-1.105-.895-2-2-2h-8z" />
                </svg>
            </button>
        </aside>

        <!-- Conteúdo -->
        <main class="flex-1 p-6 space-y-6">

            <!-- Cabeçalho -->
            <div
                class="flex flex-col md:flex-row justify-between items-start md:items-center animate__animated animate__fadeInDown">
                <div>
                    <h1 class="text-xl font-bold">Olá, Pedro Raspadinha</h1>
                    <p class="text-gray-600">Veja o resumo da Função Modelagem</p>
                </div>
                <div class="flex items-center mt-4 md:mt-0">
                    <input type="text" placeholder="Search" class="border rounded-l px-4 py-2 focus:outline-none">
                    <button class="bg-black text-white px-3 rounded-r hover:bg-gray-800 transition">🔍</button>
                </div>
            </div>

            <!-- Cards métricas -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl shadow hover:scale-105 transition animate__animated animate__fadeInUp"
                    style="animation-delay:1s!important; animation-duration:1s!important;">
                    <p class="text-gray-500">Tempo Médio de Aprovação</p>
                    <h2 id="tempoAprovacao" class="text-2xl font-bold">--</h2>
                </div>

                <div class="bg-white p-4 rounded-xl shadow hover:scale-105 transition animate__animated animate__fadeInUp"
                    style="animation-delay: 1.1s;">
                    <p class="text-gray-500">Taxa de Aprovação</p>
                    <h2 id="taxaAprovacao" class="text-2xl font-bold">--</h2>
                </div>

                <div class="bg-white p-4 rounded-xl shadow hover:scale-105 transition animate__animated animate__fadeInUp"
                    style="animation-delay: 1.2s;">
                    <p class="text-gray-500">Tempo Médio por Função</p>
                    <h2 id="tempoFuncao" class="text-2xl font-bold">--</h2>
                </div>

                <div class="bg-white p-4 rounded-xl shadow hover:scale-105 transition animate__animated animate__fadeInUp"
                    style="animation-delay: 1.3s;">
                    <p class="text-gray-500">Colaborador mais produtivo</p>
                    <h2 id="produtividade" class="text-2xl font-bold">--</h2>
                </div>
            </div>

            <!-- Status + Próximas Entregas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Gráfico -->
                <div class="bg-white p-4 rounded-xl shadow animate__animated animate__zoomIn animate__delay-1s">
                    <h3 class="font-bold mb-4">Status das Funções</h3>
                    <canvas id="statusChart" class="w-full h-48"></canvas>
                </div>

                <!-- Próximas Entregas -->
                <div class="bg-white p-4 rounded-xl shadow animate__animated animate__zoomIn"
                    style="animation-delay: 1.2s;">
                    <h3 class="font-bold mb-4">Próximas Entregas</h3>
                    <ul id="listaEntregas" class="space-y-2 text-gray-700"></ul>
                </div>

                <!-- Colaborador -->
                <div class="bg-white p-4 rounded-xl shadow flex flex-col items-center text-center animate__animated animate__zoomIn"
                    style="animation-delay: 1.3s;">
                    <img src="../../assets/logo.jpg" alt="avatar" class="rounded-full mb-2">
                    <h3 class="font-bold">Colaborador mais produtivo / efetivo</h3>
                    <p id="maisProdutivo" class="text-sm text-gray-600"></p>
                    <p id="maisAssertivo" class="text-sm text-gray-600"></p>
                </div>
            </div>

            <!-- Detalhamento -->
            <div
                class="bg-white p-4 rounded-xl shadow overflow-x-auto animate__animated animate__fadeInLeft animate__delay-2s">
                <h3 class="font-bold mb-4">Detalhamento por Função</h3>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2">Função</th>
                            <th class="p-2">Entregas</th>
                            <th class="p-2">Tempo Médio</th>
                            <th class="p-2">Aprovação</th>
                            <th class="p-2">Efetividade</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaFuncoes"></tbody>
                </table>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>

</body>

</html>