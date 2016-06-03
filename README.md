# Barge

A command line interface for developing and distributing [Airship](https://github.com/paragonie/airship) 
cabins, gadgets, and motifs.

## How to Install Barge

First, checkout our Git repository.

    git checkout https://github.com/paragonie/airship-barge.git

Next, make sure you can execute the barge command.

    cd airship-barge
    chmod u+x barge

Make sure you install the dependencies via Composer. This will get [Halite](https://github.com/paragonie/halite)
and our [constant-time encoding](https://github.com/paragonie/constant_time_encoding) library
installed (both are libraries that barge uses extensively):

    composer update

Finally, you'll want to create a symlink in `/usr/bin` so you can run barge commands
by simply typing `barge commandgoeshere`:

    ln -s ./barge /usr/bin/barge

## How to Use Barge

Recommended: Create a workspace directory.

    cd ~
    mkdir barge-workspace
    cd barge-workspace

If you don't already have a Supplier account, register one [here](https://airship.paragonie.com/register).

Now you're ready to run your first barge command.

    barge login

If this is your first time logging in, you'll need to run `barge key` twice.
(Before the final version, we intend to make this process a lot smoother.)

    barge key
    # Follow the prompts to generate your master keypair
    barge key
    # Follow the prompts to generate your signing keypair, which you'll need to sign with your master key

Once your keypairs are generated, their public counterparts are uploaded to the server
and synchronized to the entire network in an [append-only data structure](https://paragonie.com/blog/2016/05/keyggdrasil-continuum-cryptography-powering-cms-airship).

Once your keys are set up, you can begin to build CMS Airship extensions.
What do you want to build?

* A full application that can stand alone from Hull or Bridge: run `airship cabin`
* A backend modification to an existing cabin: run `airship gadget`
* A frontend modification to an existing cabin: run `airship motif`

After you follow the prompts, you should have a skeletal project directory
waiting to be fleshed out.

Ready to deploy your first version? Okay, first:

    barge build

This assembles a .phar or .zip of your extension. You can manually install
these into a local Airship to test them out (recommended). 

If you're ready to release it, first sign it with your signing key:

    barge sign

And then release it:

    barge release

If you've followed these steps, your package should be available for install
in CMS Airship. If you release an update in this manner, it should be deployed
and installed on all of your users' machines automatically (typically within an
hour, unless they changed their configuration).
