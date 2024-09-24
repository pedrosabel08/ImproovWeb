const express = require('express');
const { google } = require('googleapis');
const dotenv = require('dotenv');

dotenv.config();

const app = express();
const port = 3000;

const oauth2Client = new google.auth.OAuth2(
    process.env.CLIENT_ID,
    process.env.CLIENT_SECRET,
    process.env.REDIRECT_URI
);

// URL de autorização
const SCOPES = ['https://www.googleapis.com/auth/calendar'];

app.get('/', (req, res) => {
    const authUrl = oauth2Client.generateAuthUrl({
        access_type: 'offline',
        scope: SCOPES,
    });
    res.send(`<a href="${authUrl}">Authorize</a>`);
});

// Callback após a autorização
app.get('/callback', async (req, res) => {
    const { code } = req.query;
    const { tokens } = await oauth2Client.getToken(code);
    oauth2Client.setCredentials(tokens);

    // Criar um evento no Google Calendar
    const calendar = google.calendar({ version: 'v3', auth: oauth2Client });

    const event = {
        summary: 'Evento de Teste',
        start: {
            dateTime: new Date().toISOString(),
            timeZone: 'America/Sao_Paulo',
        },
        end: {
            dateTime: new Date(new Date().getTime() + 60 * 60 * 1000).toISOString(),
            timeZone: 'America/Sao_Paulo',
        },
    };

    calendar.events.insert({
        calendarId: 'primary',
        resource: event,
    }, (err, event) => {
        if (err) {
            console.error('Erro ao criar evento:', err);
            return res.send('Erro ao criar evento.');
        }
        res.send(`Evento criado: ${event.data.htmlLink}`);
    });
});

app.listen(port, () => {
    console.log(`Servidor rodando em http://localhost:${port}`);
});
