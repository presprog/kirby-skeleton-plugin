import { expect, test } from "vitest"

test("dispatches the frontend ready event", async () => {
  const events = []

  window.addEventListener("presprog/my-kirby-plugin:loaded", (event) => {
    events.push(event)
  })

  await import("./index.js")

  expect(events).toHaveLength(1)
  expect(events[0].detail.plugin).toBe("presprog/my-kirby-plugin")
})
