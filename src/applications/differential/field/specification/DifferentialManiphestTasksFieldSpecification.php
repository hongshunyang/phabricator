<?php

final class DifferentialManiphestTasksFieldSpecification
  extends DifferentialFieldSpecification {

  private $oldManiphestTasks = array();
  private $maniphestTasks = array();

  public function shouldAppearOnRevisionView() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorApplicationManiphest');
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getManiphestTaskPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Maniphest Tasks:';
  }

  public function renderValueForRevisionView() {
    $task_phids = $this->getManiphestTaskPHIDs();
    if (!$task_phids) {
      return null;
    }

    $links = array();
    foreach ($task_phids as $task_phid) {
      $links[] = $this->getHandle($task_phid)->renderLink();
    }

    return phutil_implode_html(phutil_tag('br'), $links);
  }

  private function getManiphestTaskPHIDs() {
    $revision = $this->getRevision();
    if (!$revision->getPHID()) {
      return array();
    }
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK);
  }

  protected function didSetRevision() {
    $this->maniphestTasks = $this->getManiphestTaskPHIDs();
    $this->oldManiphestTasks = $this->maniphestTasks;
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->maniphestTasks;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function shouldAppearOnCommitMessage() {
    return $this->shouldAppearOnRevisionView();
  }

  public function getCommitMessageKey() {
    return 'maniphestTaskPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->maniphestTasks = array_unique(nonempty($value, array()));
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Maniphest Tasks';
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Maniphest Task',
      'Maniphest Tasks',
    );
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->maniphestTasks) {
      return null;
    }

    $names = array();
    foreach ($this->maniphestTasks as $phid) {
      $handle = $this->getHandle($phid);
      $names[] = $handle->getName();
    }
    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    $matches = null;
    preg_match_all('/T(\d+)/', $value, $matches);
    if (empty($matches[0])) {
      return array();
    }

    // TODO: T603 Get a viewer here so we can issue the right query.

    $task_ids = $matches[1];
    $tasks = id(new ManiphestTask())
      ->loadAllWhere('id in (%Ld)', $task_ids);

    $task_phids = array();
    $invalid = array();
    foreach ($task_ids as $task_id) {
      $task = idx($tasks, $task_id);
      if (empty($task)) {
        $invalid[] = 'T'.$task_id;
      } else {
        $task_phids[] = $task->getPHID();
      }
    }

    if ($invalid) {
      $what = pht('Maniphest Task(s)', count($invalid));
      $invalid = implode(', ', $invalid);
      throw new DifferentialFieldParseException(
        "Commit message references nonexistent {$what}: {$invalid}.");
    }

    return $task_phids;
  }

  public function renderValueForMail($phase) {
    if ($phase == DifferentialMailPhase::COMMENT) {
      return null;
    }

    if (!$this->maniphestTasks) {
      return null;
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs($this->maniphestTasks)
      ->execute();
    $body = array();
    $body[] = 'MANIPHEST TASKS';
    foreach ($handles as $handle) {
      $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
    }
    return implode("\n", $body);
  }

  public function getCommitMessageTips() {
    return array(
      'Use "Fixes T123" in your summary to mark that the current '.
      'revision completes a given task.'
      );
  }

}
