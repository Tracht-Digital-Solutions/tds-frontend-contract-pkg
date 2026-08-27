import { describe, expectTypeOf, it } from "vitest";
import type {
  CacheRefreshReport,
  SiteConnectionIdentityView,
  SiteConnectionView,
  SitePairingDeliveryView,
} from "../index.js";

describe("site connection wire types", () => {
  it("exports only secret-free browser views", () => {
    expectTypeOf<SiteConnectionView>().not.toHaveProperty("site_key");
    expectTypeOf<SiteConnectionView>().not.toHaveProperty("cache_token");
    expectTypeOf<SitePairingDeliveryView>().not.toHaveProperty("pairing_token");
    expectTypeOf<SiteConnectionIdentityView["bindings"]>().toEqualTypeOf<Record<string, unknown>>();
    expectTypeOf<CacheRefreshReport["cached"]>().toEqualTypeOf<boolean>();
  });
});
