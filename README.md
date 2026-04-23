# WP Social Ninja – Social Feed, Reviews & Chat Plugin for WP (Google Reviews, Photo Feeds & More)


#### DB Changelog

- `waiting_since` in fs_tickets has been added



#### Run the project

- `npm install`
- `npx mix watch` or `npm run watch`
- `npx mix --production`

##### Run the tests
If you don't have a testing database then please run the following command from your project root:
- ` bin/install-wp-tests.sh db_name db_user db_pass db_host`

You may need to install `phpunit` package as dev deps. To run the tests,
just run `phpunit` command in the terminal from project root. To get more
details on `phpunit` type `phpunit --help` on your terminal. You can test
both free and pro version at the same time. All test file names should start
with `test-`. Make sure you have all your test file in the `tests` folder.
You can include or exclude your files in `phpunit.xml.dist` file.


### Build for release
`sh ./build.sh --node-build --with-pro`
