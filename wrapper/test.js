let index = require('./index');

(async () => {
    try {
        let event = {test: true, puppetPath: process.argv[2]};
        console.log("starting puppeter with " + event.puppetPath);

        let result = await index.handler(event);
        console.log("---------\nresult:\n---------\n\n", result.body ? Buffer.from(result.body, 'base64').toString() : result);
    } catch (e) {
        console.log("error:", e);
    }
})();