# Twingle Campaign

The *Twingle Campaign* campaign type does, unlike the other campaign types (*Twingle Project* & *Twingle Event*), not
represent an entity on the Twingle side.

The *Twingle Campaign*  is can be used to track the origin of a donation. In order to achieve this, it takes the URL of
it's *Twingle Project* parent campaign and adds a `cid` parameter to its end that will be sent to Twingle and back. With
the `cid` coming back from Twingle via API call to *TwingleDonation.submit* (provided by the **Twingle API** extension)
the donation can get assigned to the originally *Twingle Campaign*.

**Attention:** The *Twingle Campaign* must always be the child (or grandchild) of a *Twingle Project*.

You can use the *Twingle Campaign* url for example for newsletters or social media posts. The url will lead the users to
the Twingle donation form of the parent *Twingle Project* but thanks to the `cid`, the donation will be assigned to
the *Twingle Campaign*.