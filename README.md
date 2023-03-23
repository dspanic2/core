# Core App

## Local Setup with Docker

IMPORTANT: Use GitBash on WIndows to run all the commands as .sh scripts won't execute properly othervise.

1. Install Docker if you don't have it already.
2. Install git with GitBash on Windows
2. Clone the repo.
3. Inside the project folder, run:

    `git clone https://github.com/pagach/docker-compose-lamp.git devsetup &&
     chmod +x ./devsetup/setup.sh &&
     ./devsetup/setup.sh`

4. Start server wit `./devsetup/scripts/server-start.sh` and check the URL at the end
4. Copy .env.example to .env and change content if necessary
5. Run composer install with `docker-compose -f devsetup/docker-compose.yml exec webserver composer install`

At this point the localsetup should be complete!

To maintain the database you can add a new connection to navicat:

    HOST: 127.0.0.1
    PORT: 3306
    USERNAME: [check devsetup/.env]
    PASSWORD: [check devsetup/.env]

or you can use phpMyAdmin on port 8080.

### Docker windows
Check WINDOWS.md file

### Commands
- Server start/restart `./devsetup/scripts/server-start.sh`
- Symfony rebuild assets `docker-compose -f devsetup/docker-compose.yml exec webserver ./scripts/rebuild.sh`
- Codestyle check `docker-compose -f devsetup/docker-compose.yml exec webserver ./scripts/run_phpcs.sh`
- Codestyle fix and check `./scripts/run_phpcbf.sh`

### Other useful commands
- Clearing cache: `docker-compose -f devsetup/docker-compose.yml exec php php bin/console cache:clear`
- Check for service existence: `docker-compose -f devsetup/docker-compose.yml exec php php bin/console debug:autowiring`

## PHPCS
- To check coding style execute `docker-compose -f devsetup/docker-compose.yml exec webserver composer phpcs`
- To automatically fix coding style run `docker-compose -f devsetup/docker-compose.yml exec webserver composer phpcbf`

https://github.com/djoos/Symfony-coding-standard
https://github.com/squizlabs/PHP_CodeSniffer/wiki/Advanced-Usage

# Composer specific

Running composer install executes several composer defined scripts (symfony scripts) which will fail if the project
is not fully operational locally but that can be ignored since it happens after the install/update process is complete.

## Setting up composer mode
- Install composer
- Run `ssh -T git@bitbucket.org` to add bitbucket to known_hosts
- Execute `composer install` (from terminal or Tools->Composer->Install)
- If composer complains about memory exhausted try https://stackoverflow.com/a/58919692
- Make sure you have a local SSH key generated and added to your bitbucket account

## Updating bundle code
- Make sure bundle GITs are added in PHPstorm: File->Settings->Version Control and add all bundle repositories
- Checkout 1.x branch of the bundle (either in PHPstorm or navigate to bundle in terminal and run `git checkout 1.x`)
- Add your new code
- Open commit command (make sure changes are grouped by repository so you commit/push only repository changes; little
squares icon)
- Replace bundle commit reference in composer.lock with new commit hash and commit and push (alternatively
go to Tools->Composer->Manage dependencies, select bundle and update; taks more time)
- SSH to server and run composer install in the project (unless this was added to deploy script in the meantime)
- If there are entities that should be rebuilt (classes and/or doctrine) also execute `composer rebuild-bundle [BUNDLE NAME]`
where BUNDLE NAME is full name eg. AppBundle

NOTE: Bundles don't currently use versions (tags) but use the 1.x branch directly

## Migrate existing bundle to composer repo
- Create new repo in bitbucket (Core project)
- Right click on bundle main file (eg. TaskBusinessBundle.php) and select Open in terminal
- Initialize repo:

        git init
        git remote add origin ... (COPY EXACT COMMAND FROM EMPTY REPO PAGE)
        git checkout -b 1.x

- Copy composer.json file existing bundle and edit content to fit the new bundle
- If there are entities, remove them (Entity folder and Resource->Config->Doctrine)
- Add new bundle to project .gitignore

        git add .
        git commit -m "Init repo"
        git push
        COPY NEW COMMAND THAT IS DISPLAYED IN CONSOLE AND EXECUTE IT

- Add new bundle to composer.json
- Execute `composer update --lock`

## Update bundle with new code

- To update a single bundle execute `composer update shipshapesolutions/BUNDLE_NAME`
- Uo update all shipshape bundles execute `composer update shipshapesolutions/*`
