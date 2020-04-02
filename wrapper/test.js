let index = require('./index');

(async () => {
    let event = {test: true, puppetPath: process.argv[2]};
    let context = {succeed: (result) => console.log("---------\nresult:\n---------\n\n", result.body ? Buffer.from(result.body, 'base64').toString() : result)};

    console.log("starting puppeter with " + event.puppetPath);
    index.handler(event, context);
})();