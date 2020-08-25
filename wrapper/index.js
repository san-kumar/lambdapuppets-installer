exports.handler = async (event) => {
    let body = '';
    let result = null;
    let browser = null;

    try {
        const pipes = require('./pipes');
        const helpers = require('./helpers');

        const handler = event.path || 'index';
        const module = require(event.puppetPath || ('/var/task/' + handler.replace(/^\//, '') + '.js'));
        const config = module.config || {};

        if (typeof module.run !== 'function')
            throw new Error('module.exports.run function is missing in ' + handler + '.js');

        if (config.browser === 'none') {
            browser = null;
        } else if (config.browser === 'zombie') {
            const zombie = require('zombie');
            browser = new zombie();
        } else {
            if (event.test === true) {
                const puppeteer = require('puppeteer');
                browser = await puppeteer.launch({headless: false, slowMo: 200});
            } else {
                const chromium = require('chrome-aws-lambda');
                const {addExtra} = require('puppeteer-extra');
                const StealthPlugin = require('puppeteer-extra-plugin-stealth');

                process.env.LD_LIBRARY_PATH = '/opt/chrome/lib:' + process.env.LD_LIBRARY_PATH;
                process.env.FONTCONFIG_PATH = '/opt/chrome/';

                const puppeteerExtra = addExtra(chromium.puppeteer);
                puppeteerExtra.use(StealthPlugin());

                browser = await puppeteerExtra.launch({
                    args: chromium.args,
                    defaultViewport: chromium.defaultViewport,
                    executablePath: '/opt/chrome/chromium',
                    headless: true,
                    userDataDir: '/tmp'
                });
            }
        }

        helpers(browser);
        body = await module.run(browser, event);

        if ((typeof body == 'object') && body.statusCode)
            return body;

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
    } catch (e) {
        if (event && event.test)
            console.log("error: ", e);

        body = '<pre>ERROR name: ' + e.name + ' message: ' + e.message + ' at: ' + e.stack + '</pre>';
    } finally {
        if (browser && (typeof browser.close == 'function')) {
            await browser.close();
        }
    }

    return {statusCode: 200, body: Buffer.from((body || '').toString()).toString('base64'), headers: {'Access-Control-Allow-Origin': '*', 'Content-Type': 'text/html'}, isBase64Encoded: true};
};
