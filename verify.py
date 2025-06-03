#!/usr/bin/env python3

import argparse
import hmac
import json
import sys
from pathlib import Path

def validate_file(arg):
    file = Path(arg)
    if file.is_file():
        return file
    else:
        print(f"Fatal: no file exists at: {file}")
        sys.exit(1)

def generate_legal_anons(voters, key):
    voter_map = {}
    for voter in voters:
        if voter in voter_map.keys():
            print(f"Warning: {voter} appears in the voters list multiple times")
            continue
        voter_map[voter] = hmac.digest(key.encode(), voter.encode(), "sha256").hex()[0:20]

    return voter_map



def is_valid_uuvt(uuvt, key):
    parts = uuvt.split('.')
    if len(parts) != 3:
        return False
    
    msg = '.'.join(parts[0:2])
    return hmac.digest(key.encode(), msg.encode(), "sha256").hex() == parts[2]



def verify_uuvts(legal_anons, uuvts, key):
    legal_anons = set(legal_anons)

    print("Filtering UUVTs before checking signatures")

    # remove duplicate anonymous ids from the list of legal anons
    anons = [uuvt.split('.')[0] for uuvt in uuvts]

    seen = set()
    dupes = {anon for anon in anons if anon in seen or seen.add(anon)}
    legal_anons = legal_anons.difference(dupes)

    invalid_uuvts = [uuvt for uuvt in uuvts if len(uuvt.split('.')) != 3]
    invalid_uuvts += [uuvt for uuvt in uuvts if uuvt.split('.')[0] not in legal_anons]

    uuvts = [uuvt for uuvt in uuvts if uuvt not in invalid_uuvts]
    
    print("The following UUVTs were either invalid, contained duplicate anonymous ids or did not have a legal anonymous id:")
    print("\n".join(invalid_uuvts)) 

    for uuvt in uuvts:
        print(f"Checking: {uuvt}")
        if not is_valid_uuvt(uuvt, key):
            print(f"Invalid uuvt found: {uuvt}")
            invalid_uuvts.append(uuvt)

    return invalid_uuvts




if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--ufp', type=validate_file, help='The file path containing UUVTs to be verified each seperated by newlines', required=True)
    parser.add_argument('--vfp', type=validate_file, help='The file path containing the list of registered voters', required=True)
    parser.add_argument('--of', type=Path, help='The file path to output the invalid UUVTs too', required=True)
    parser.add_argument('--deanon', type=Path, help='The file path to output the deanonymised json file too, if not set it will not be dumped', required=False)
    args = parser.parse_args()
    secret_key = input("Please enter the secret key from https://qwrky.dev/sdvote/admin.php?oldkey : ")

    uuvts = [line.strip() for line in open(args.ufp, 'r').readlines()]


    

    voters = [line.strip() for line in open(args.vfp, 'r').readlines()]
    print("Info: Generating legal anons list")
    legal_anons = generate_legal_anons(voters, secret_key)
    if args.deanon:
        with open(args.deanon, 'w') as of:
            of.write(json.dumps(legal_anons))

    illegal_uuvts = verify_uuvts(legal_anons.values(), uuvts, secret_key)
    with open(args.of, 'w') as of:
        of.write('\n'.join(illegal_uuvts))












