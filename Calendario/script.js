document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: 'getObraPrazos.php',
        eventClick: function (info) {
            alert('Obra: ' + info.event.title + '\nData: ' + info.event.start.toISOString().slice(0, 10) + '\nTipo entrega: ' + info.event.extendedProps.tipo_entrega);
        },
        eventDidMount: function (info) {
            var tipoEntrega = info.event.extendedProps.tipo_entrega;
            console.log('Evento:', info.event);
            console.log('Tipo de entrega:', tipoEntrega);

            var eventColor = '';
            if (tipoEntrega) {
                switch (tipoEntrega.trim().toLowerCase()) {
                    case 'primeira entrega':
                        eventColor = '#03b6fc';
                        break;
                    case 'entrega final':
                        eventColor = '#28a745';
                        break;
                    case 'alteração':
                        eventColor = '#ff8000';
                        break;
                    case 'entrega parcial':
                        eventColor = '#a442f5';
                        break;
                    case 'entrega antecipada':
                        eventColor = '#a2ff00';
                        break;
                    case 'entrega tarefa':
                        eventColor = '#ff006a';
                        break;
                    default:
                        eventColor = '#cccccc'; 
                        break;
                }
            } else {
                eventColor = '#cccccc';
            }

            console.log('Cor do evento:', eventColor);
            info.el.style.backgroundColor = eventColor;
            info.el.style.border = 'none';
        }
    });
    calendar.render();

    $('#addEventBtn').on('click', function () {
        $('#addEventModal').modal('show');
    });

    $('#addEventForm').on('submit', function (e) {
        e.preventDefault();

        var obraId = $('#opcao_obra').val();
        var prazo = $('#prazoDate').val();
        var tipoEntrega = $('#tipoEntrega').val();
        var assuntoEntrega = $('#assuntoEntrega').val();
        var usuariosIds = $('#usuario_id').val();

        if (obraId && prazo && tipoEntrega && assuntoEntrega) {
            $.ajax({
                url: 'addObraPrazo.php',
                method: 'POST',
                data: {
                    obra_id: obraId,
                    prazo: prazo,
                    assuntoEntrega: assuntoEntrega,
                    tipo_entrega: tipoEntrega,
                    usuarios_ids: usuariosIds
                },
                success: function (response) {
                    var result = JSON.parse(response);
                    if (result.success) {

                        var eventColor = '';
                        switch (tipoEntrega.trim().toLowerCase()) {  
                            case 'primeira entrega':
                                eventColor = '#03b6fc';
                                break;
                            case 'entrega final':
                                eventColor = '#28a745';
                                break;
                            case 'alteração':
                                eventColor = '#ff8000';
                                break;
                            case 'entrega parcial':
                                eventColor = '#a442f5';
                                break;
                            case 'entrega antecipada':
                                eventColor = '#a2ff00';
                                break;
                            case 'entrega tarefa':
                                eventColor = '#ff006a';
                                break;
                            default:
                                eventColor = '#cccccc'; 
                                break;
                        }

                        calendar.addEvent({
                            title: assuntoEntrega,
                            start: prazo,
                            color: eventColor,
                            allDay: true
                        });
                        $('#addEventModal').modal('hide');
                    } else {
                        alert('Erro ao adicionar o evento: ' + result.message);
                    }
                },
                error: function () {
                    alert('Erro ao se comunicar com o servidor.');
                }
            });
        } else {
            alert("Por favor, preencha todos os campos.");
        }
    });
});

document.getElementById('logObraSelect').addEventListener('change', function () {
    const obraId = this.value;
    const logObraTable = document.getElementById('logObraTable');
    const tableBody = logObraTable.querySelector('tbody');

    if (obraId) {
        tableBody.innerHTML = '';

        fetch('buscarPrazosObra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `obraId=${obraId}`,
        })
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    data.forEach(prazo => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${prazo.prazo}</td><td>${prazo.assunto_entrega}</td>`;
                        tableBody.appendChild(row);
                    });
                    logObraTable.style.display = 'table';
                } else {
                    logObraTable.style.display = 'none';
                }
            })
            .catch(error => console.error('Erro:', error));
    } else {
        logObraTable.style.display = 'none';
    }
});


document.addEventListener("DOMContentLoaded", function () {

    document.getElementById('menuButton').addEventListener('click', function () {
        const menu = document.getElementById('menu');
        menu.classList.toggle('hidden');
    });

    window.addEventListener('click', function (event) {
        const menu = document.getElementById('menu');
        const button = document.getElementById('menuButton');

        if (!button.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.add('hidden');
        }
    });

});