const AWS = require('aws-sdk');
const fetch = require('node-fetch');
const config = require('./config');

module.exports = function (browser) {
    global.llp_page = async (url, cookies = null) => {
        const page = await browser.newPage();

        await page.setViewport({width: 0, height: 0});
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36');

        if (url) await page.goto(url);

        if (cookies && cookies.length > 0) {
            for (let cookie of cookies) {
                let {name, value} = cookie;
                await page.setCookie({name, value});
            }

            await page.goto(url);
        }

        global.llp_last_page = page;
        return page;
    };

    global.llp_click = async (selector, waitAfter = 2500, thePage = null) => {
        let page = thePage || global.llp_last_page;
        let ele = await page.$(selector);

        if (!ele) return false;

        await ele.click();
        await page.waitFor(waitAfter);

        return true;
    };

    global.llp_click_by_text = async (textRegEx, selector = 'a', waitAfter = 2500, thePage = null) => {
        let page = thePage || global.llp_last_page;

        let found = await page.evaluate((selector, textRegEx) => {
            let regex = new RegExp(textRegEx, 'i'), html = '';
            let matches = [...document.querySelectorAll(selector)].filter(e => e.textContent.match(regex));
            let found = (matches && matches.length);
            if (found) matches[0].click();
            return found;
        }, selector, textRegEx);

        if (found) {
            await page.waitFor(waitAfter);
        }

        return found;
    };

    global.llp_screenshot = async (options = {fullPage: true}, thePage = null) => {
        let page = thePage || global.llp_last_page;

        let clientId = config.imgurApiKey || '13bb473ddae76b9';
        let image = await page.screenshot(Object.assign(options, {encoding: 'base64'}));

        return await new Promise((resolve, reject) => {
            fetch('https://api.imgur.com/3/image.json', {method: 'POST', body: JSON.stringify({image: image.toString()}), headers: {'Authorization': 'Client-ID ' + clientId, 'Content-Type': 'application/json'},})
                .then(res => res.json())
                .then(json => resolve(json.data.link))
                .catch(() => reject());
        });
    };

    global.llp_upload = async (Bucket, Key, Body, ContentType = 'text/html') => {
        let s3 = new AWS.S3();
        let params = {Bucket, Key, Body, ACL: 'public-read', ContentType};

        return await new Promise((resolve, reject) => {
            s3.putObject(params, function (err, data) {
                if (err) reject(err);
                else resolve(`https://${Bucket}.s3.amazonaws.com/${Key}`);
            });
        });
    }
};