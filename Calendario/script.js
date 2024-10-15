document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: 'getObraPrazos.php', 
        eventClick: function (info) {
            alert('Obra: ' + info.event.title + '\nData: ' + info.event.start.toISOString().slice(0, 10));
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

        if (obraId && prazo && tipoEntrega) {
            $.ajax({
                url: 'addObraPrazo.php',
                method: 'POST',
                data: {
                    obra_id: obraId,
                    prazo: prazo,
                    tipo_entrega: tipoEntrega
                },
                success: function (response) {
                    var result = JSON.parse(response);
                    if (result.success) {

                        calendar.addEvent({
                            title: tipoEntrega,
                            start: prazo,
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