import { expect, test } from "vitest"
import ExampleField from "./fields/example.js"
import "./index.js"

test("registers the Panel plugin", () => {
  expect(panel.plugin).toHaveBeenCalledWith("presprog/my-kirby-plugin", {
    fields: {
      "my-plugin-example": ExampleField
    }
  })
})
