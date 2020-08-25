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
        console.log("bot_id: ", bot_id);
        return new Promise((resolve, reject) => {
            let params = new URLSearchParams();
            let strip_tags = (input, allowed) => {
                allowed = (((allowed || '') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('')
                var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi
                var commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi
                return input.replace(commentsAndPhpTags, '').replace(tags, function ($0, $1) {
                    return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
                })
            };
            params.append('chat_id', chat_id);
            params.append('text', strip_tags(htmlMsg, '<i><b><u><strike><a><code><pre>'));
            params.append('parse_mode', 'HTML');

            https.get('https://api.telegram.org/' + bot_id + '/sendMessage?' + params.toString(), (res) => {
                res.on('data', (d) => {
                    console.log("d: ", d.toString());
                    resolve('https://api.telegram.org/' + bot_id + '/sendMessage?' + params.toString());
                });
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
    },
};