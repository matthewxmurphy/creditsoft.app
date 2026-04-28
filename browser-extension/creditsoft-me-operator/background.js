async function ensureSidePanelBehavior() {
  try {
    await chrome.sidePanel.setPanelBehavior({ openPanelOnActionClick: true });
  } catch {
    // Ignore and rely on explicit open below.
  }
}

chrome.runtime.onInstalled.addListener(() => {
  ensureSidePanelBehavior();
});

chrome.runtime.onStartup?.addListener(() => {
  ensureSidePanelBehavior();
});

chrome.action.onClicked?.addListener(async (tab) => {
  await ensureSidePanelBehavior();

  try {
    if (tab?.id) {
      await chrome.sidePanel.open({ tabId: tab.id });
      return;
    }

    if (typeof tab?.windowId === 'number') {
      await chrome.sidePanel.open({ windowId: tab.windowId });
    }
  } catch {
    // If Chrome refuses the explicit open, the setPanelBehavior path still gives us a fallback.
  }
});
