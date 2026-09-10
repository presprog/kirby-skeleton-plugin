import { expect, test } from "vitest"
import "./index.js"

test("registers the Panel plugin", () => {
  expect(panel.plugin).toHaveBeenCalledWith("presprog/my-kirby-plugin", {})
})
