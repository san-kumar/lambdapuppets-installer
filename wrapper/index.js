const chromium = require('chrome-aws-lambda');
const zombie = require('zombie');
const pipes = require('./pipes');

exports.handler = async (event, context) => {
    let result = null;
    let browser = null;
    let body = '';

    try {
        const handler = event.path || 'index';
        const module = require(event.puppetPath || ('/var/task/' + handler.replace(/^\//, '') + '.js'));
        const config = module.config || {};

        if (typeof module.run !== 'function')
            throw new Error('module.exports.run function is missing in ' + handler + '.js');

        if (config.browser === 'none') {
            browser = null;
        } else if (config.browser === 'zombie') {
            browser = new zombie();
        } else {
            browser = await chromium.puppeteer.launch({
                args: chromium.args,
                defaultViewport: chromium.defaultViewport,
                executablePath: await chromium.executablePath,
                headless: chromium.headless,
            });
        }

        body = await module.run(browser, event);

        if ((typeof body == 'object') && body.statusCode)
            return context.succeed(body);

        if (body) {
            if (typeof config.pipe == 'object') {
                let pipe = config.pipe;

                if (typeof pipe.telegram == 'object') {
                    await pipes.telegram(pipe.telegram.bot_id, pipe.telegram.chat_id, body);
                }

                if (typeof pipe.email == 'object') {
                    await pipes.email(pipe.email.from, pipe.email.to, pipe.email.subject || 'lambdapuppets', body);
                }

                if (typeof pipe.url == 'object') {
                    await pipes.ping_url(pipe.url.target, {body});
                }
            }
        }
    } catch (error) {
        body = "Failed: " + error.toString();
    } finally {
        if (browser && (typeof browser.close == 'function')) {
            await browser.close();
        }
    }

    return context.succeed({statusCode: 200, body: Buffer.from(body).toString('base64'), headers: {'Access-Control-Allow-Origin': '*', 'Content-Type': 'text/html'}, isBase64Encoded: true});
};

