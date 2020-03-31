const AWS = require('aws-sdk');
const https = require('https');
const url = require('url');

module.exports = {
    email(from, to, subject, htmlBody) {
        var params = {
            Destination: {
                ToAddresses: [to]
            },
            Message: { /* required */
                Body: { /* required */
                    Html: {
                        Charset: "UTF-8",
                        Data: htmlBody
                    }
                },
                Subject: {
                    Charset: 'UTF-8',
                    Data: subject
                }
            },
            Source: from,
        };

        return new AWS.SES({apiVersion: '2010-12-01'}).sendEmail(params).promise();
    },
    telegram(bot_id, chat_id, htmlMsg) {
        return new Promise((resolve, reject) => {
            let params = new URLSearchParams();
            params.append('chat_id', chat_id);
            params.append('text', htmlMsg);
            params.append('parse_mode', 'HTML');

            https.get('https://api.telegram.org/' + bot_id + '/sendMessage?' + params.toString(), (res) => {
                res.on('data', (d) => resolve('https://api.telegram.org/' + bot_id + '/sendMessage?' + params.toString()));
            }).on('error', (e) => {
                reject(e);
            });
        });
    },
    ping_url(target_url, body) {
        return new Promise((resolve, reject) => {
            const myURL = url.parse(target_url);
            const isJson = typeof body == 'object';
            const data = isJson ? JSON.stringify(body) : body || '';

            const options = {
                hostname: myURL.hostname,
                port: myURL.port || 443,
                path: myURL.path,
                method: 'POST',
                headers: Object.assign({'Content-Length': data.length}, isJson ? {'Content-Type': 'application/json'} : {}),
            };

            const req = https.request(options, (res) => {
                res.on('data', (d) => resolve(d))
            });

            req.on('error', (error) => reject(error));

            req.write(data);
            req.end();
        });
    }
};