<?php

class Indev extends GitBase {

  /**
   * Отображает все гит-проекты
   */
  function projects() {
    foreach ($this->findGitFolders() as $f) print '* '.basename($f)."\n";
  }

  /**
   * Отображает активные ветки всех гит-проектов
   */
  function branches() {
    foreach ($this->findGitFolders() as $folder) {
      print '* '.str_pad(basename($folder), 20).(new GitFolder($folder))->wdBranch()."\n";
    }
  }

  function done($force = false) {
    if (!$this->commit([], $force)) {
      output('Aborting "done" action');
      return;
    }
    $this->push([], $force);
    $this->deploy();
  }

  /**
   * Комитит проекты, нуждающиеся в пуше или пуле
   */
  function commit($projectsFilter = [], $force = false) {
    return $this->abstractConfirmAction($projectsFilter, 'commit', 'getNotCleanFolders', 'You trying to commit these projects', $force);
  }

  /**
   * Резетит проекты до мастер-ветки и переключает на неё
   */
  function reset($force = false) {
    $folders = $this->getNotCleanFolders();
    $confirm = $this->abstractConfirmAction([], 'reset', 'getNotCleanFolders', 'You trying to reset these projects', $force);
    if ($confirm) {
      foreach ($folders as $folder) {
        chdir($folder);
        `git checkout master`;
      }
    }
    return $confirm;
  }

  /**
   * Комитит проекты, нуждающиеся в пуше или пуле и не являющиеся issue
   */
  function commitNonIssue($projectsFilter = [], $force = false) {
    return $this->abstractConfirmAction($projectsFilter, 'commit', 'getNotCleanFoldersExceptingIssues', 'You trying to commit these projects', $force);
  }


  /**
   * Синхронизирует изменения с ремоутом
   */
  function push($projectsFilter = [], $force = false) {
    return $this->abstractConfirmAction($projectsFilter, 'push', 'getChangedFolders', 'You trying to push these projects to all theirs remotes', $force);
  }

  function deploy() {
    $config =  require __DIR__.'/config.php';
    foreach ((array)$config['deploy'] as $cmd) {
      output2($config['deploy']);
      shell_exec($cmd);
    }
  }

  protected function abstractConfirmAction($projectsFilter, $actionMethod, $getFoldersMethod, $confirmCaption, $force = false) {
    $folders = $this->$getFoldersMethod($projectsFilter);
    if (!$folders) {
      print "No projects to $actionMethod\n";
      return true;
    }
    if (!$force) {
      print "$confirmCaption:\n";
      $projectsInfoAction = $actionMethod.'Info';
      if (method_exists($this, $projectsInfoAction)) $this->$projectsInfoAction($folders);
      if (!Cli::confirm('Are you sure?')) return false;
    }
    foreach ($folders as $folder) {
      if (!(new GitFolder($folder))->$actionMethod()) {
        output("$actionMethod failed on folder '$folder'");
        return false;
      }
    }
    return true;
  }

  protected function pushInfo(array $folders) {
    foreach ($folders as $folder) {
      $git = new GitFolder($folder);
      $remotes = implode(', ', $git->getRemotes($git->wdBranch()));
      if (!$remotes) $remotes = 'origin (new)';
      print '* '.str_pad(basename($folder), 20).str_pad($git->wdBranch(), 10).'> '.$remotes."\n";
    }
  }

  protected function commitInfo(array $folders) {
    foreach ($folders as $folder) {
      $git = new GitFolder($folder);
      print '* '.str_pad(basename($folder), 20).$git->wdBranch()."\n";
    }
  }

  protected function resetInfo(array $folders) {
    $this->commitInfo($folders);
  }

  protected function getNotCleanFoldersExceptingIssues($filter = []) {
    return array_filter($this->getNotCleanFolders($filter), function($folder) {
      return !Misc::hasPrefix('i-', (new GitFolder($folder))->wdBranch());
    });
  }

  protected function getNotCleanFolders($filter = []) {
    return array_filter($this->findGitFolders($filter), function($folder) {
      $gitFolder = new GitFolder($folder);
      return !$gitFolder->isClean() or !$gitFolder->onBranch();
    });
  }

  protected function getChangedFolders($filter = []) {
    return array_filter($this->findGitFolders($filter), function($folder) {
      return (new GitFolder($folder))->hasChanges();
    });
  }

}
