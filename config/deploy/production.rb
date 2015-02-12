set :application, 'wp-multisite-sso'
set :repo_url, "git@github.com:voceconnect/#{fetch(:application)}.git"

set :scm, 'git-to-svn'
set :type, 'plugin'

set :svn_repository, "http://plugins.svn.wordpress.org/wp-multisite-sso/"
set :svn_deploy_to, "trunk"

set :build_folders, (
  fetch(:build_folders) << %w{
    *config
  }
).flatten